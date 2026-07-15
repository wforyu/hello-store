<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = auth()->user()->orders()
            ->with(['items', 'payment'])
            ->latest()
            ->paginate(10);

        $orders->getCollection()->transform(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'success' => true,
            'data' => $orders,
            'message' => null,
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        $order->load(['items', 'payment', 'address']);

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order, true),
            'message' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'payment_method' => 'required|in:manual_transfer,cod',
            'notes' => 'nullable|string|max:500',
            'address_id' => 'required|exists:addresses,id',
            'use_points' => 'nullable|integer|min:0',
            'coupon_code' => 'nullable|string',
            'shipping_courier' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $addressOwned = Address::where('id', $request->address_id)
            ->where('user_id', auth()->id())
            ->exists();
        if (! $addressOwned) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Alamat tidak ditemukan.',
            ], 422);
        }

        $productIds = collect($request->items)->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');
        $variantIds = collect($request->items)->pluck('variant_id')->filter();
        $liveVariants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        // Validate stock
        foreach ($request->items as $itemData) {
            $product = $liveProducts->get($itemData['product_id']);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Produk dengan ID '.$itemData['product_id'].' tidak ditemukan atau tidak aktif.',
                ], 422);
            }

            if (! empty($itemData['variant_id'])) {
                $variant = $liveVariants->get($itemData['variant_id']);
                if (! $variant || $variant->stock < $itemData['quantity']) {
                    $availableStock = $variant ? $variant->stock : 0;

                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => "Stok varian '{$product->name}' tidak mencukupi (tersedia: {$availableStock}).",
                    ], 422);
                }
            } else {
                if ($product->stock < $itemData['quantity']) {
                    return response()->json([
                        'success' => false,
                        'data' => null,
                        'message' => "Stok '{$product->name}' tidak mencukupi (tersedia: {$product->stock}).",
                    ], 422);
                }
            }
        }

        // Calculate
        $subtotal = 0;
        foreach ($request->items as $itemData) {
            $product = $liveProducts->get($itemData['product_id']);
            $price = (float) $product->price;
            if (! empty($itemData['variant_id'])) {
                $variant = $liveVariants->get($itemData['variant_id']);
                $price = (float) ($variant->price ?? $product->price);
            }
            $subtotal += $price * $itemData['quantity'];
        }

        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppnRate = (int) Setting::get('ppn_percentage', 11);

        $couponDiscount = 0;
        $couponId = null;
        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon && $coupon->isValid() && $coupon->canUseBy(auth()->user()) && $subtotal >= $coupon->min_order) {
                $couponDiscount = (float) $coupon->calculateDiscount($subtotal);
                $couponId = $coupon->id;
            }
        }

        $ppnBase = max(0, $subtotal - $couponDiscount);
        $ppnAmount = $ppnEnabled ? round($ppnBase * $ppnRate / 100) : 0;

        $shippingCost = (float) $request->shipping_cost;

        $usePoints = min((int) ($request->use_points ?? 0), auth()->user()->points);
        $pointDiscount = 0;
        if ($usePoints > 0) {
            $maxPointDiscount = (int) floor(($ppnBase + $shippingCost) * 0.5);
            $usePoints = min($usePoints, $maxPointDiscount);
            $pointDiscount = $usePoints;
        }

        $memberDiscountRate = auth()->user()->getSegmentDiscountRate();
        $memberDiscount = $memberDiscountRate > 0 ? (int) round($ppnBase * $memberDiscountRate) : 0;

        $total = $ppnBase + $shippingCost + $ppnAmount - $pointDiscount - $memberDiscount;

        $order = DB::transaction(function () use ($request, $subtotal, $shippingCost, $couponDiscount, $couponId, $ppnAmount, $ppnRate, $total, $liveProducts, $liveVariants, $usePoints, $pointDiscount) {
            $order = Order::create([
                'user_id' => auth()->id(),
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'shipping_courier' => $request->shipping_courier,
                'discount' => $couponDiscount,
                'coupon_id' => $couponId,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'unpaid',
                'notes' => ($request->notes ?? '').($ppnAmount > 0 ? ' | PPN '.$ppnRate.'%: Rp '.number_format($ppnAmount, 0, ',', '.') : '').($pointDiscount > 0 ? ' | Poin: Rp '.number_format($pointDiscount, 0, ',', '.') : '').($couponDiscount > 0 ? ' | Kupon: -Rp '.number_format($couponDiscount, 0, ',', '.') : '').($memberDiscount > 0 ? ' | Diskon Member '.strtoupper(auth()->user()->segment).': -Rp '.number_format($memberDiscount, 0, ',', '.') : ''),
                'address_id' => $request->address_id,
            ]);

            foreach ($request->items as $itemData) {
                $product = $liveProducts->get($itemData['product_id']);
                $price = (float) $product->price;
                $variantName = null;

                if (! empty($itemData['variant_id'])) {
                    $variant = $liveVariants->get($itemData['variant_id']);
                    $price = (float) ($variant->price ?? $product->price);
                    $variantName = $variant->name;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => ! empty($itemData['variant_id']) ? $itemData['variant_id'] : null,
                    'product_name' => $variantName ? $product->name.' - '.$variantName : $product->name,
                    'product_price' => $price,
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $price * $itemData['quantity'],
                ]);

                if (! empty($itemData['variant_id'])) {
                    $v = $liveVariants->get($itemData['variant_id']);
                    if ($v) {
                        $v->decrement('stock', $itemData['quantity']);
                        $p = $liveProducts->get($itemData['product_id']);
                        if ($p) {
                            $p->recordStockHistory(-$itemData['quantity'], 'order', 'Varian: '.$v->name, Order::class, $order->id);
                        }
                    }
                } else {
                    $p = $liveProducts->get($itemData['product_id']);
                    if ($p) {
                        $p->decrement('stock', $itemData['quantity']);
                        $p->recordStockHistory(-$itemData['quantity'], 'order', null, Order::class, $order->id);
                    }
                }
            }

            if ($request->payment_method === 'manual_transfer') {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => 'manual_transfer',
                    'amount' => $total,
                    'status' => 'pending',
                ]);
            }

            if ($usePoints > 0) {
                auth()->user()->redeemPoints($usePoints, 'Poin ditukar untuk pesanan #'.$order->order_number, $order);
            }

            if ($couponId) {
                $coupon = Coupon::find($couponId);
                if ($coupon) {
                    $coupon->users()->attach(auth()->id());
                }
            }

            return $order;
        });

        Cart::where('user_id', auth()->id())->each(function ($cart) {
            $cart->items()->delete();
            $cart->delete();
        });

        Notification::createForUser(
            auth()->id(),
            'order',
            'Pesanan #'.$order->order_number.' berhasil dibuat',
            'Status: Menunggu pembayaran.',
            null,
            null
        );

        Notification::createForAdmins(
            'order',
            'Pesanan Baru #'.$order->order_number,
            'Pesanan baru dari '.auth()->user()->name,
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order->load(['items', 'payment']), true),
            'message' => 'Pesanan berhasil dibuat.',
        ], 201);
    }

    public function paymentUpload(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        $validated = $request->validate([
            'proof_image' => 'required|image|max:2048',
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
        ]);

        $payment = $order->payment;
        if (! $payment) {
            $payment = new Payment(['order_id' => $order->id]);
        } else {
            if ($payment->proof_image) {
                Storage::disk('public')->delete($payment->proof_image);
            }
        }

        $payment->method = 'manual_transfer';
        $payment->amount = $order->total;
        $payment->status = 'paid';
        $payment->paid_at = now();
        $payment->bank_name = $validated['bank_name'];
        $payment->account_name = $validated['account_name'];
        $payment->account_number = $validated['account_number'];
        $uploadedFile = $request->file('proof_image');
        $payment->proof_image = $uploadedFile ? $uploadedFile->store('payments', 'public') : $payment->proof_image;
        $payment->save();

        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
        ]);

        Notification::createForUser(
            $order->user_id,
            'order',
            'Pembayaran Pesanan #'.$order->order_number.' diterima',
            'Pesanan sedang diproses.',
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Bukti pembayaran berhasil diupload.',
        ]);
    }

    public function confirmReceived(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak dalam status dikirim.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'payment_status' => 'paid',
            ]);

            $user = auth()->user();
            $user->increment('total_spent', $order->total);

            $pointsRate = $user->getPointsRate();
            $pointsEarned = (int) floor($order->total * $pointsRate);
            if ($pointsEarned > 0) {
                $user->addPoints($pointsEarned, 'Poin dari pesanan #'.$order->order_number, $order);
            }

            $user->autoUpgradeSegment();
        });

        Notification::createForUser(
            $order->user_id,
            'order',
            'Pesanan #'.$order->order_number.' telah diterima',
            'Terima kasih telah berbelanja!',
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Pesanan telah diterima. Terima kasih!',
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Hanya pesanan dengan status menunggu yang dapat dibatalkan.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->load('items.product');

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            foreach ($order->items as $item) {
                if (! empty($item->product_variant_id)) {
                    $variant = ProductVariant::find($item->product_variant_id);
                    if ($variant) {
                        $variant->increment('stock', $item->quantity);
                    }
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->recordStockHistory($item->quantity, 'order', 'Pembatalan pesanan #'.$order->order_number.' - varian', Order::class, $order->id);
                    }
                } else {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $product->increment('stock', $item->quantity);
                        $product->recordStockHistory($item->quantity, 'order', 'Pembatalan pesanan #'.$order->order_number, Order::class, $order->id);
                    }
                }
            }
        });

        Notification::createForUser(
            $order->user_id,
            'order',
            'Pesanan #'.$order->order_number.' dibatalkan',
            'Pesanan Anda telah berhasil dibatalkan.',
            null,
            null
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
    }

    public function reorder(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Pesanan tidak ditemukan.',
            ], 403);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Hanya pesanan selesai yang dapat dipesan ulang.',
            ], 422);
        }

        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);
        $order->load('items');

        $added = 0;
        $skipped = 0;

        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);

            if (! $product || ! $product->is_active || $product->stock < 1) {
                $skipped++;

                continue;
            }

            $existingCartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $item->product_id)
                ->whereNull('product_variant_id')
                ->first();

            if ($existingCartItem) {
                $newQty = min($existingCartItem->quantity + $item->quantity, $product->stock);
                if ($newQty > $existingCartItem->quantity) {
                    $existingCartItem->update([
                        'quantity' => $newQty,
                        'price' => $product->price,
                    ]);
                    $added++;
                } else {
                    $skipped++;
                }
            } else {
                $qty = min($item->quantity, $product->stock);
                if ($qty > 0) {
                    CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $item->product_id,
                        'quantity' => $qty,
                        'price' => $product->price,
                    ]);
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'added' => $added,
                'skipped' => $skipped,
            ],
            'message' => $added.' produk ditambahkan ke keranjang'.($skipped > 0 ? ' ('.$skipped.' produk dilewati).' : '.'),
        ]);
    }

    private function formatOrder($order, bool $detail = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_label' => match ($order->status) {
                'pending' => 'Menunggu Pembayaran',
                'processing' => 'Diproses',
                'shipped' => 'Dikirim',
                'delivered' => 'Selesai',
                'cancelled' => 'Dibatalkan',
                'refunded' => 'Diretur',
                default => $order->status,
            },
            'subtotal' => (float) $order->subtotal,
            'subtotal_formatted' => 'Rp'.number_format($order->subtotal, 0, ',', '.'),
            'shipping_cost' => (float) $order->shipping_cost,
            'shipping_courier' => $order->shipping_courier,
            'total' => (float) $order->total,
            'total_formatted' => 'Rp'.number_format($order->total, 0, ',', '.'),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'payment_status_label' => match ($order->payment_status) {
                'unpaid' => 'Belum Dibayar',
                'paid' => 'Lunas',
                'refunded' => 'Dikembalikan',
                default => $order->payment_status,
            },
            'notes' => $order->notes,
            'created_at' => $order->created_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'cancelled_at' => $order->cancelled_at,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_price' => (float) $item->product_price,
                'product_price_formatted' => 'Rp'.number_format($item->product_price, 0, ',', '.'),
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'subtotal_formatted' => 'Rp'.number_format($item->subtotal, 0, ',', '.'),
            ]),
        ];

        if ($order->relationLoaded('payment') && $order->payment) {
            $payment = $order->payment;
            $data['payment'] = [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'proof_image_url' => $payment->proof_image ? '/storage/'.$payment->proof_image : null,
                'bank_name' => $payment->bank_name,
                'account_name' => $payment->account_name,
                'account_number' => $payment->account_number,
                'paid_at' => $payment->paid_at,
            ];
        }

        if ($detail && $order->relationLoaded('address') && $order->address) {
            $addr = $order->address;
            $data['address'] = [
                'label' => $addr->label,
                'recipient' => $addr->recipient,
                'phone' => $addr->phone,
                'street' => $addr->street,
                'city' => $addr->city,
                'province' => $addr->province,
                'postal_code' => $addr->postal_code,
            ];
        }

        return $data;
    }
}
