<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PosException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PosService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * POS API for the desktop client.
 *
 * The cart is never stored server side. Every endpoint takes the cart
 * inline, which is what lets the desktop client run a full offline POS:
 * the cart lives in its own local database and only reaches the server as
 * part of a checkout or a sync payload.
 */
class PosController extends Controller
{
    public function __construct(protected PosService $pos) {}

    /**
     * Everything the client needs to boot: PPN rules, payment methods, the
     * store details the receipt header is printed from, and the server clock it
     * will later use as a delta-sync cursor.
     *
     * The store details belong here rather than in a second call because the
     * whole config is cached in the client's local database, so a receipt
     * printed on a disconnected till still carries a real address and phone.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'store_name' => Setting::get('store_name', config('app.name')),
            'store_address' => Setting::get('store_address', ''),
            'store_phone' => Setting::get('phone', ''),
            'store_whatsapp' => Setting::get('whatsapp', ''),
            'ppn_enabled' => Setting::get('ppn_enabled', '0') === '1',
            'ppn_percentage' => (int) Setting::get('ppn_percentage', 11),
            'payment_methods' => PosService::PAYMENT_METHODS,
            'price_drift_tolerance' => PosService::PRICE_DRIFT_TOLERANCE,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Product catalog for the client's local database.
     *
     * Pass ?since=<ISO8601> to receive only what changed after that cursor.
     * The returned `server_time` is captured *before* the query runs, so the
     * client can use it as the next cursor without ever missing a row that
     * was written while the previous page was in flight. Soft-deleted
     * products are included and flagged so the client can purge them.
     */
    public function catalog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        $cursor = now();

        // Parse the cursor into a Carbon instance rather than binding the raw
        // ISO string. The DB stores "Y-m-d H:i:s" while ISO8601 uses a "T"
        // separator, so a raw string comparison silently matches nothing on
        // SQLite (' ' < 'T') and only happens to work on MySQL.
        $since = isset($validated['since'])
            ? Carbon::parse($validated['since'])
            : null;

        $products = Product::withTrashed()
            ->with(['productImages', 'category:id,name'])
            ->when($since, fn ($q, $cursorDate) => $q->where('updated_at', '>', $cursorDate))
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(fn ($qq) => $qq->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%'));
            })
            ->when($validated['category_id'] ?? null, function ($q, $categoryId) {
                $ids = Category::where('id', $categoryId)
                    ->orWhere('parent_id', $categoryId)
                    ->pluck('id');
                $q->whereIn('category_id', $ids);
            })
            ->orderBy('id')
            ->limit($validated['per_page'] ?? 500)
            ->get();

        return response()->json([
            'server_time' => $cursor->toIso8601String(),
            'count' => $products->count(),
            'products' => $products->map(fn (Product $p) => $this->formatProduct($p))->values(),
        ]);
    }

    /**
     * Online checkout. Strict stock mode: a shortfall rejects the order.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $this->validateCheckoutPayload($request);

        try {
            $result = $this->pos->checkout(
                $request->user(),
                $this->buildCart($validated['items']),
                $validated + ['mode' => PosService::MODE_STRICT]
            );
        } catch (PosException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($this->formatCheckoutResult($result, 'ok'));
    }

    /**
     * Replay of sales that already happened on a disconnected client.
     *
     * Every entry carries a client_ref, and (client_id, client_ref) is unique
     * on the orders table, so a retried batch is a no-op rather than a second
     * order. Each entry is processed in its own transaction: one bad entry
     * must not discard the sales around it.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid',
            'orders' => 'required|array|min:1|max:100',
            'orders.*' => 'required|array',
            'orders.*.client_ref' => 'required|uuid',
            'orders.*.paid_at' => 'nullable|date',
            'orders.*.items' => 'required|array|min:1',
            'orders.*.items.*.product_id' => 'required|integer',
            'orders.*.items.*.name' => 'required|string|max:255',
            'orders.*.items.*.price' => 'required|numeric|min:0',
            'orders.*.items.*.quantity' => 'required|integer|min:1',
            'orders.*.items.*.discount' => 'nullable|numeric|min:0',
            'orders.*.items.*.discount_type' => 'nullable|in:nominal,percent',
            'orders.*.customer_name' => 'nullable|string|max:100',
            'orders.*.customer_id' => 'nullable|integer|exists:users,id',
            'orders.*.amount_paid' => 'nullable|numeric|min:0',
            'orders.*.discount' => 'nullable|numeric|min:0',
            'orders.*.ppn' => 'nullable|boolean',
            'orders.*.payment_method' => 'required|in:'.implode(',', PosService::PAYMENT_METHODS),
            'orders.*.order_type' => 'required|in:dine_in,takeaway',
        ]);

        $clientId = $validated['client_id'];
        $results = [];

        foreach ($validated['orders'] as $payload) {
            $clientRef = $payload['client_ref'];

            $existing = Order::where('client_id', $clientId)
                ->where('client_ref', $clientRef)
                ->first();

            if ($existing) {
                $results[] = $this->formatExistingOrder($existing);

                continue;
            }

            try {
                $result = $this->pos->checkout(
                    $request->user(),
                    $this->buildCart($payload['items']),
                    $payload + [
                        'mode' => PosService::MODE_CLAMP,
                        'client_id' => $clientId,
                        'client_ref' => $clientRef,
                    ]
                );

                $results[] = $this->formatCheckoutResult($result, 'synced');
            } catch (PosException $e) {
                $results[] = [
                    'client_ref' => $clientRef,
                    'status' => 'rejected',
                    'message' => $e->getMessage(),
                ];
            } catch (QueryException $e) {
                // Two retries of the same entry raced each other. The unique
                // index already settled it -- report the winner.
                $duplicate = Order::where('client_id', $clientId)
                    ->where('client_ref', $clientRef)
                    ->first();

                if ($duplicate) {
                    $results[] = $this->formatExistingOrder($duplicate);

                    continue;
                }

                throw $e;
            }
        }

        $conflicts = array_values(array_filter($results, fn (array $r) => ($r['stock_conflict'] ?? false) === true));
        $rejected = array_values(array_filter($results, fn (array $r) => $r['status'] === 'rejected'));

        return response()->json([
            'client_id' => $clientId,
            'synced_at' => now()->toIso8601String(),
            'summary' => [
                'total' => count($results),
                'synced' => count(array_filter($results, fn (array $r) => $r['status'] === 'synced')),
                'duplicate' => count(array_filter($results, fn (array $r) => $r['status'] === 'duplicate')),
                'rejected' => count($rejected),
                'stock_conflicts' => count($conflicts),
            ],
            'results' => $results,
        ]);
    }

    /**
     * Reconcile view: what the server holds for this client's synced sales.
     * The desktop client polls this after reconnecting to learn which of its
     * offline sales landed and which need an admin's attention.
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|uuid',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $orders = Order::where('client_id', $validated['client_id'])
            ->latest('id')
            ->limit($validated['per_page'] ?? 50)
            ->get();

        return response()->json([
            'client_id' => $validated['client_id'],
            'count' => $orders->count(),
            'orders' => $orders->map(fn (Order $o) => [
                'client_ref' => $o->client_ref,
                'order_id' => $o->id,
                'order_number' => $o->order_number,
                'total' => (float) $o->total,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'stock_conflict' => (bool) $o->stock_conflict,
                'conflict_reason' => $o->conflict_reason,
                'price_drift' => $o->admin_notes,
                'synced_at' => $o->synced_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * The cashier's own POS sales, for the client's transaction history.
     */
    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'conflicts_only' => 'nullable|boolean',
        ]);

        $orders = Order::where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->when($validated['conflicts_only'] ?? false, fn ($q) => $q->where('stock_conflict', true))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $orders->through(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total' => (float) $o->total,
                'payment_method' => $o->payment_method,
                'payment_status' => $o->payment_status,
                'stock_conflict' => (bool) $o->stock_conflict,
                'conflict_reason' => $o->conflict_reason,
                'created_at' => $o->created_at?->toIso8601String(),
            ])->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCheckoutPayload(Request $request): array
    {
        return $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|in:nominal,percent',
            'customer_name' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer|exists:users,id',
            'amount_paid' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'ppn' => 'nullable|boolean',
            'payment_method' => 'required|in:'.implode(',', PosService::PAYMENT_METHODS),
            'order_type' => 'required|in:dine_in,takeaway',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildCart(array $items): Collection
    {
        return collect($items)->map(fn (array $item) => [
            'product_id' => (int) $item['product_id'],
            'name' => (string) $item['name'],
            'price' => (float) $item['price'],
            'quantity' => (int) $item['quantity'],
            'discount' => (float) ($item['discount'] ?? 0),
            'discount_type' => ($item['discount_type'] ?? 'nominal') === 'percent' ? 'percent' : 'nominal',
        ])->values();
    }

    /**
     * @param  array{order: Order, change: float, conflicts: array<int, string>, price_drift: array<int, string>, quote: array<string, mixed>}  $result
     * @return array<string, mixed>
     */
    protected function formatCheckoutResult(array $result, string $status): array
    {
        $order = $result['order'];
        $conflicts = $result['conflicts'];

        return [
            'client_ref' => $order->client_ref,
            'status' => $status,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'change' => $result['change'],
            'ppn_amount' => $result['quote']['ppn_amount'],
            'discount' => $result['quote']['global_discount'],
            'stock_conflict' => $conflicts !== [],
            'conflict_reason' => $conflicts === [] ? null : implode("\n", $conflicts),
            'price_drift' => $result['price_drift'],
            'message' => $conflicts === []
                ? 'Transaksi tersimpan.'
                : 'Transaksi tersimpan, tetapi ada selisih stok yang perlu diperiksa admin.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatExistingOrder(Order $order): array
    {
        return [
            'client_ref' => $order->client_ref,
            'status' => 'duplicate',
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'change' => 0.0,
            'ppn_amount' => 0.0,
            'discount' => 0.0,
            'stock_conflict' => (bool) $order->stock_conflict,
            'conflict_reason' => $order->conflict_reason,
            'price_drift' => [],
            'message' => 'Transaksi ini sudah pernah tersinkron, tidak diduplikasi.',
        ];
    }

    /**
     * Image paths are returned relative and rooted at /storage/ so the client
     * can prepend whichever API host it is currently pointed at.
     *
     * @return array<string, mixed>
     */
    protected function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'is_active' => (bool) $product->is_active,
            'image' => $this->relativeImage($product->main_image),
            'updated_at' => $product->updated_at?->toIso8601String(),
            'deleted' => $product->trashed(),
        ];
    }

    protected function relativeImage(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return '/'.ltrim($path, '/');
    }
}
