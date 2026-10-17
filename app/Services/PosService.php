<?php

namespace App\Services;

use App\Exceptions\PosException;
use App\Exceptions\PosStockException;
use App\Exceptions\PosValidationException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single source of truth for every POS money and stock decision.
 *
 * The web POS (/pos, session cart) and the API POS (/api/pos/*, stateless
 * cart for the desktop client) both go through this class, so the two can
 * never drift on pricing, PPN base, discounts or stock handling.
 *
 * Two stock modes:
 *  - MODE_STRICT  online sale. Stock is checked at write time; a shortfall
 *                 aborts the whole order with a PosStockException.
 *  - MODE_CLAMP   replay of a sale that already happened on a disconnected
 *                 client. The order is always written at the full quantity
 *                 the cashier charged for, the stock decrement is clamped to
 *                 whatever is left, and the order is flagged stock_conflict
 *                 so an admin can reconcile the ledger later.
 */
class PosService
{
    public const MODE_STRICT = 'strict';

    public const MODE_CLAMP = 'clamp';

    public const PAYMENT_METHODS = ['cash', 'qris', 'debit', 'transfer'];

    /**
     * Percentage gap between the price actually charged and the live catalog
     * price above which the order is annotated for admin review. Below this
     * the difference is rounding noise and not worth surfacing.
     */
    public const PRICE_DRIFT_TOLERANCE = 5.0;

    /**
     * Map the POS payment method to the value stored on orders/payments.
     */
    public const PAYMENT_METHOD_MAP = [
        'cash' => 'cash',
        'qris' => 'qris',
        'debit' => 'debit_card',
        'transfer' => 'bank_transfer',
    ];

    /**
     * Compute the money breakdown for a cart without touching the database
     * beyond reading the PPN settings. Safe to call for live previews.
     *
     * @param  Collection<int, array>  $cart
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function quote(Collection $cart, array $options = []): array
    {
        $subtotal = (float) $cart->sum(
            fn ($item) => (float) $item['price'] * (int) $item['quantity']
        );

        $itemDiscounts = (float) $cart->sum(fn ($item) => $this->calcItemDiscount($item));

        $afterItemDiscount = $subtotal - $itemDiscounts;

        // Clamp the global discount: it can never eat more than what is left
        // after per-item discounts, otherwise the total would go negative.
        $globalDiscount = min(
            (float) ($options['discount'] ?? 0),
            max(0, $afterItemDiscount)
        );

        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $ppnApplied = $ppnEnabled && (bool) ($options['ppn'] ?? false);

        // Indonesian PPN is levied on the DPP, i.e. the price after *all*
        // discounts. Never on the raw subtotal.
        $ppnBase = max(0, $afterItemDiscount - $globalDiscount);
        $ppnAmount = $ppnApplied ? round($ppnBase * $ppnRate / 100) : 0;

        $total = max(0, $ppnBase + $ppnAmount);

        return [
            'subtotal' => $subtotal,
            'item_discounts' => $itemDiscounts,
            'after_item_discount' => $afterItemDiscount,
            'global_discount' => $globalDiscount,
            'ppn_enabled' => $ppnEnabled,
            'ppn_applied' => $ppnApplied,
            'ppn_rate' => $ppnRate,
            'ppn_base' => $ppnBase,
            'ppn_amount' => $ppnAmount,
            'total' => $total,
        ];
    }

    /**
     * Discount for a single cart line, honouring the per-item type.
     *
     * @param  array<string, mixed>  $item
     */
    public function calcItemDiscount(array $item): float
    {
        $itemTotal = (float) $item['price'] * (int) $item['quantity'];
        $discount = (float) ($item['discount'] ?? 0);

        if ($discount <= 0) {
            return 0;
        }

        if (($item['discount_type'] ?? 'nominal') === 'percent') {
            return round($itemTotal * min($discount, 100) / 100);
        }

        return min($discount, $itemTotal);
    }

    /**
     * Turn a cart into a paid order.
     *
     * @param  Collection<int, array>  $cart
     * @param  array<string, mixed>  $options
     * @return array{order: Order, change: float, conflicts: array<int, string>, quote: array<string, mixed>}
     *
     * @throws PosException
     */
    public function checkout(User $cashier, Collection $cart, array $options = []): array
    {
        $mode = ($options['mode'] ?? self::MODE_STRICT) === self::MODE_CLAMP
            ? self::MODE_CLAMP
            : self::MODE_STRICT;

        if ($cart->isEmpty()) {
            throw new PosValidationException('Keranjang kosong!');
        }

        $paymentMethod = (string) ($options['payment_method'] ?? '');
        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new PosValidationException('Metode pembayaran tidak valid.');
        }

        $quote = $this->quote($cart, $options);

        $amountPaid = $options['amount_paid'] ?? null;
        if ($paymentMethod === 'cash' && ($amountPaid === null || (float) $amountPaid < $quote['total'])) {
            throw new PosValidationException('Jumlah dibayar kurang dari total!');
        }

        $productIds = $cart->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        // Resolved outside the transaction: the shift row is not contended,
        // and querying it inside would either deadlock-prone re-read or need
        // a lock we do not otherwise require.
        $activeShift = $cashier->openShift();

        // lockForUpdate serialises concurrent cashiers on the same rows, so
        // the second of two racing checkouts reads the already-decremented
        // stock instead of a stale copy.
        $result = DB::transaction(function () use (
            $cashier, $cart, $options, $quote, $paymentMethod, $mode, $productIds, $activeShift
        ) {
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $resolved = $this->resolveStock($cart, $products, $mode);
            $conflicts = $resolved['conflicts'];
            $drift = $resolved['price_drift'];

            $order = Order::create([
                'user_id' => $cashier->id,
                'shift_id' => $activeShift?->id,
                'client_id' => $options['client_id'] ?? null,
                'client_ref' => $options['client_ref'] ?? null,
                'order_number' => 'POS-'.strtoupper(Str::random(8)),
                'status' => 'completed',
                'subtotal' => $quote['subtotal'],
                'shipping_cost' => 0,
                'total' => $quote['total'],
                'payment_method' => self::PAYMENT_METHOD_MAP[$paymentMethod],
                'payment_status' => 'paid',
                'stock_conflict' => $conflicts !== [],
                'conflict_reason' => $conflicts === [] ? null : implode("\n", $conflicts),
                'admin_notes' => $drift === [] ? null : $this->formatPriceDrift($drift),
                'synced_at' => ! empty($options['client_id']) ? $this->syncedAt($options) : null,
                'notes' => $this->buildNotes($options, $quote),
            ]);

            foreach ($resolved['lines'] as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'product_name' => $line['name'],
                    'product_price' => $line['price'],
                    'quantity' => $line['quantity'],
                    // The cashier charged for the full quantity. A stock
                    // conflict is a bookkeeping problem, never a revenue one.
                    'subtotal' => $line['gross'] - $line['discount'],
                ]);

                if ($line['deduct'] > 0) {
                    $this->applyStockDeduction($line['product'], $line['deduct'], $order);
                }
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => self::PAYMENT_METHOD_MAP[$paymentMethod],
                'amount' => $quote['total'],
                'status' => 'paid',
                'paid_at' => $this->paidAt($options),
            ]);

            return ['order' => $order, 'conflicts' => $conflicts, 'drift' => $drift];
        }, 3);

        $change = $paymentMethod === 'cash'
            ? max(0, (float) $amountPaid - $quote['total'])
            : 0.0;

        return [
            'order' => $result['order'],
            'change' => $change,
            'conflicts' => $result['conflicts'],
            'price_drift' => $result['drift'],
            'quote' => $quote,
        ];
    }

    /**
     * Work out, per cart line, how much stock can actually be taken.
     *
     * The cart carries the price the cashier actually charged, and for a
     * replayed offline sale that price is the one on the printed receipt, so
     * it stays authoritative. A divergence from the live catalog price is
     * only recorded, never silently repriced.
     *
     * @param  Collection<int, array>  $cart
     * @param  Collection<int, Product>  $products
     * @return array{lines: array<int, array<string, mixed>>, conflicts: array<int, string>, price_drift: array<int, string>}
     */
    protected function resolveStock(Collection $cart, Collection $products, string $mode): array
    {
        $lines = [];
        $conflicts = [];
        $drift = [];

        foreach ($cart as $item) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);
            $quantity = max(1, (int) $item['quantity']);
            $available = (int) ($product?->stock ?? 0);
            $name = (string) $item['name'];
            $soldPrice = (float) $item['price'];

            if ($product === null) {
                throw new PosStockException("Produk '{$name}' tidak tersedia.");
            }

            if ($available < $quantity && $mode === self::MODE_STRICT) {
                throw new PosStockException(
                    "Stok '{$name}' tidak mencukupi (tersedia: {$available})!"
                );
            }

            $deduct = min($quantity, $available);

            if ($deduct < $quantity) {
                $conflicts[] = sprintf(
                    "Stok '%s' tidak mencukupi saat sinkronisasi (diminta %d, tersedia %d).",
                    $name,
                    $quantity,
                    $available
                );
            }

            $currentPrice = (float) $product->price;
            if ($currentPrice > 0 && abs($soldPrice - $currentPrice) / $currentPrice * 100 > self::PRICE_DRIFT_TOLERANCE) {
                $drift[] = sprintf(
                    "Harga '%s' saat transaksi Rp %s, harga katalog saat ini Rp %s.",
                    $name,
                    number_format($soldPrice, 0, ',', '.'),
                    number_format($currentPrice, 0, ',', '.')
                );
            }

            $lines[] = [
                'product' => $product,
                'product_id' => (int) $item['product_id'],
                'name' => $name,
                'price' => $soldPrice,
                'quantity' => $quantity,
                'gross' => $soldPrice * $quantity,
                'discount' => $this->calcItemDiscount($item),
                'deduct' => $deduct,
            ];
        }

        return ['lines' => $lines, 'conflicts' => $conflicts, 'price_drift' => $drift];
    }

    /**
     * Persist a stock decrement and log it.
     *
     * saveQuietly() is required: the Product `saved` hook writes its own
     * 'manual' stock history entry, which would double-count this sale.
     *
     * The in-memory stock is updated *before* recordStockHistory() so the
     * history row stores stock_before = the pre-decrement value and
     * stock_after = the post-decrement value.
     */
    protected function applyStockDeduction(Product $product, int $deduct, Order $order): void
    {
        $stockAfter = max(0, $product->stock - $deduct);

        $product->stock = $stockAfter;
        $product->saveQuietly();

        $product->recordStockHistory(
            -$deduct,
            'pos',
            null,
            Order::class,
            $order->id
        );
    }

    /**
     * Build the single-line notes string the receipt views parse.
     *
     * pos/print-receipt splits the string on '|' and matches each segment
     * against /^PPN (\d+)%: Rp ([\d.]+)$/, so the PPN segment is emitted last
     * purely to keep the segments in reading order; the parsers themselves
     * are position independent.
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $quote
     */
    protected function buildNotes(array $options, array $quote): string
    {
        $orderType = ($options['order_type'] ?? 'dine_in') === 'takeaway' ? 'Takeaway' : 'Dine-in';

        $notes = $orderType.' - '.($options['customer_name'] ?? 'Umum');

        if (! empty($options['customer_id'])) {
            $notes .= ' | Customer ID: '.$options['customer_id'];
        }

        if ($quote['global_discount'] > 0) {
            $notes .= ' | Diskon: Rp '.number_format($quote['global_discount'], 0, ',', '.');
        }

        if ($quote['ppn_applied']) {
            $notes .= ' | PPN '.$quote['ppn_rate'].'%: Rp '.number_format($quote['ppn_amount'], 0, ',', '.');
        }

        return $notes;
    }

    /**
     * @param  array<int, string>  $drift
     */
    protected function formatPriceDrift(array $drift): string
    {
        return 'Selisih harga (>'.self::PRICE_DRIFT_TOLERANCE."%):\n".implode("\n", $drift);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function paidAt(array $options): mixed
    {
        return ! empty($options['paid_at']) ? $options['paid_at'] : now();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function syncedAt(array $options): mixed
    {
        return ! empty($options['synced_at']) ? $options['synced_at'] : now();
    }
}
