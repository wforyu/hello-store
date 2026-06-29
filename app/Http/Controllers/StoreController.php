<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDownload;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\StockHistory;
use App\Models\Wishlist;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function home()
    {
        $categories = Category::whereNull('parent_id')->with('children')->get();
        $featuredProducts = Product::with('productImages')->where('featured', true)->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest()->take(8)->get();
        $latestProducts = Product::with('productImages')->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest()->take(8)->get();

        return view('store.home', compact('categories', 'featuredProducts', 'latestProducts'));
    }

    public function products(Request $request)
    {
        $query = Product::with('productImages')->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $childIds = $category->children()->pluck('id')->push($category->id);
                $query->whereIn('category_id', $childIds);
            }
        }

        $sort = $request->get('sort', 'terbaru');
        match ($sort) {
            'termurah' => $query->orderBy('price'),
            'termahal' => $query->orderByDesc('price'),
            'nama' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $products = $query->paginate(12);
        $categories = Category::whereNull('parent_id')->with('children')->get();

        return view('store.products', compact('products', 'categories'));
    }

    public function productDetail($slug)
    {
        $product = Product::with('productImages')->where('slug', $slug)->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->firstOrFail();
        $relatedProducts = Product::with('productImages')->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->take(4)
            ->get();

        $reviews = $product->approvedReviews()->with('user')->latest()->get();

        $userReview = auth()->check()
            ? Review::where('product_id', $product->id)->where('user_id', auth()->id())->first()
            : null;

        $recentlyViewed = collect(session('recently_viewed', []))
            ->filter(fn ($id) => $id !== $product->id)
            ->prepend($product->id)
            ->take(12)
            ->values()
            ->toArray();
        session(['recently_viewed' => $recentlyViewed]);

        return view('store.product-detail', compact('product', 'relatedProducts', 'reviews', 'userReview'));
    }

    public function cartIndex()
    {
        $cart = collect(session('cart', []));

        return view('store.cart', compact('cart'));
    }

    public function cartAdd(Request $request, Product $product)
    {
        if ($product->stock < 1) {
            return redirect()->back()->with('error', 'Stok produk habis!');
        }

        $cart = collect(session('cart', []));
        $existing = $cart->firstWhere('product_id', $product->id);
        $qty = $request->quantity ?? 1;

        if ($existing) {
            $cart = $cart->map(function ($item) use ($product, $qty) {
                if ($item['product_id'] === $product->id) {
                    $item['quantity'] = min($item['quantity'] + $qty, $product->stock);
                }

                return $item;
            });
        } else {
            $cart->push([
                'product_id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'image' => $product->main_image,
                'quantity' => min($qty, $product->stock),
                'stock' => $product->stock,
            ]);
        }

        session(['cart' => $cart]);

        return redirect()->back()->with('success', 'Produk ditambahkan ke keranjang!');
    }

    public function cartUpdate(Request $request)
    {
        $cart = collect(session('cart', []));

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $cart = $cart->map(function ($item) use ($request, $liveProducts) {
            if ($request->has('quantity_'.$item['product_id'])) {
                $liveStock = $liveProducts->get($item['product_id'])?->stock ?? $item['stock'];
                $item['quantity'] = max(1, min($liveStock, (int) $request->input('quantity_'.$item['product_id'])));
                $item['stock'] = $liveStock;
            }

            return $item;
        });

        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Keranjang diperbarui!');
    }

    public function cartRemove($productId)
    {
        $cart = collect(session('cart', []));
        $cart = $cart->where('product_id', '!=', (int) $productId)->values();
        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Produk dihapus dari keranjang!');
    }

    public function checkout()
    {
        $cart = collect(session('cart', []));
        if ($cart->isEmpty()) {
            return redirect()->route('products.index')->with('error', 'Keranjang kosong!');
        }

        $addresses = auth()->user()->addresses;
        $subtotal = $cart->sum(fn ($item) => $item['price'] * $item['quantity']);

        $totalWeight = $this->getCartWeight($cart);
        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $ppnAmount = $ppnEnabled ? round($subtotal * $ppnRate / 100) : 0;
        $shippingRates = [];
        $selectedAddress = request()->query('address_id')
            ? $addresses->firstWhere('id', (int) request()->query('address_id'))
            : $addresses->first();

        if ($selectedAddress) {
            $shippingService = new ShippingService;
            $shippingRates = $shippingService->getRates($selectedAddress->city, $totalWeight);
        }

        return view('store.checkout', compact('cart', 'addresses', 'subtotal', 'shippingRates', 'totalWeight', 'ppnEnabled', 'ppnRate', 'ppnAmount'));
    }

    public function placeOrder(Request $request)
    {
        $cart = collect(session('cart', []));
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Keranjang kosong!');
        }

        $validated = $request->validate([
            'address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|in:manual_transfer,cod',
            'notes' => 'nullable|string|max:500',
            'shipping_courier' => 'required|string',
            'shipping_service' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $address = Address::where('id', $validated['address_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($cart as $item) {
            $product = $liveProducts->get($item['product_id']);
            if (! $product || $product->stock < $item['quantity']) {
                $availableStock = $product ? $product->stock : 0;

                return redirect()->route('cart.index')->with('error', "Stok '{$item['name']}' tidak mencukupi (tersedia: {$availableStock})!");
            }
        }

        $subtotal = $cart->sum(fn ($item) => $item['price'] * $item['quantity']);
        $shippingCost = (int) $validated['shipping_cost'];
        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $ppnAmount = $ppnEnabled ? round($subtotal * $ppnRate / 100) : 0;

        $discountAmount = 0;
        $coupon = null;
        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon && $coupon->canUseBy(auth()->user())) {
                $discountAmount = $coupon->calculateDiscount($subtotal);
            }
        }

        $total = $subtotal + $shippingCost + $ppnAmount - $discountAmount;

        $order = DB::transaction(function () use ($cart, $subtotal, $shippingCost, $ppnAmount, $ppnRate, $total, $validated, $address, $liveProducts, $coupon, $discountAmount) {
            $order = Order::create([
                'user_id' => auth()->id(),
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'status' => 'pending',
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'shipping_courier' => $validated['shipping_courier'].' - '.$validated['shipping_service'],
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'notes' => $validated['notes'].($ppnAmount > 0 ? ' | PPN '.$ppnRate.'%: Rp '.number_format($ppnAmount, 0, ',', '.') : ''),
                'address_id' => $address->id,
                'coupon_id' => $coupon?->id,
                'discount' => $discountAmount,
            ]);

            foreach ($cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);

                $p = $liveProducts->get($item['product_id']);
                if ($p) {
                    $p->decrement('stock', $item['quantity']);
                    $p->recordStockHistory(-$item['quantity'], 'order', null, Order::class, $order->id);
                }
            }

            if ($validated['payment_method'] === 'manual_transfer') {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => 'manual_transfer',
                    'amount' => $total,
                    'status' => 'pending',
                ]);
            }

            if ($coupon) {
                $coupon->increment('used_count');
                $coupon->users()->attach(auth()->id(), ['order_id' => $order->id]);
            }

            return $order;
        });

        Notification::createForUser(
            auth()->id(),
            'order',
            'Pesanan #'.$order->order_number.' berhasil dibuat',
            'Status: Menunggu pembayaran. Silakan lakukan pembayaran.',
            null,
            route('orders.show', $order)
        );

        Notification::createForAdmins(
            'order',
            'Pesanan Baru #'.$order->order_number,
            'Pesanan baru dari '.auth()->user()->name,
            null,
            route('orders.show', $order)
        );

        session()->forget('cart');

        return redirect()->route('orders.show', $order->id)->with('success', 'Pesanan berhasil dibuat!');
    }

    public function orders()
    {
        $orders = auth()->user()->orders()
            ->with(['items', 'payment', 'address'])
            ->latest()
            ->paginate(10);

        return view('store.orders', compact('orders'));
    }

    public function orderShow(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $order->load(['items', 'payment', 'address']);

        return view('store.order-detail', compact('order'));
    }

    public function paymentUpload(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
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
        $payment->proof_image = $request->file('proof_image')->store('payments', 'public');
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
            route('orders.show', $order)
        );

        return redirect()->route('orders.show', $order->id)->with('success', 'Bukti pembayaran berhasil diupload!');
    }

    public function confirmReceived(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== 'shipped') {
            return back()->with('error', 'Pesanan tidak dalam status dikirim.');
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
            'payment_status' => 'paid',
        ]);

        Notification::createForUser(
            $order->user_id,
            'order',
            'Pesanan #'.$order->order_number.' telah diterima',
            'Terima kasih telah berbelanja! Jangan lupa beri ulasan.',
            null,
            route('orders.show', $order)
        );

        return redirect()->route('orders.show', $order->id)->with('success', 'Pesanan telah diterima. Terima kasih!');
    }

    public function cancelOrder(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        if ($order->status !== 'pending') {
            return back()->with('error', 'Hanya pesanan dengan status Pending yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($order) {
            $order->load('items.product');

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'payment_status' => 'unpaid',
            ]);

            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                    $item->product->recordStockHistory(
                        $item->quantity,
                        'order',
                        'Pembatalan pesanan #'.$order->order_number,
                        Order::class,
                        $order->id
                    );
                }
            }
        });

        return redirect()->route('orders.show', $order->id)->with('success', 'Pesanan berhasil dibatalkan.');
    }

    public function processRefund(Order $order)
    {
        if (! in_array($order->status, ['processing', 'shipped', 'delivered'])) {
            return back()->with('error', 'Pesanan tidak dapat diretur dengan status saat ini.');
        }

        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Pesanan belum dibayar.');
        }

        DB::transaction(function () use ($order) {
            $order->items()->with('product')->each(function ($item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);

                    StockHistory::create([
                        'product_id' => $item->product_id,
                        'user_id' => auth()->id(),
                        'type' => 'refund',
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'quantity_change' => $item->quantity,
                        'stock_before' => $item->product->stock - $item->quantity,
                        'stock_after' => $item->product->stock,
                        'notes' => 'Retur pesanan #'.$order->order_number,
                    ]);
                }
            });

            $order->update([
                'status' => 'refunded',
                'payment_status' => 'refunded',
            ]);
        });

        Notification::createForUser(
            $order->user_id,
            'order',
            'Pesanan #'.$order->order_number.' telah diretur',
            'Dana akan dikembalikan. Stok barang sudah dikembalikan.',
            null,
            route('orders.show', $order)
        );

        return back()->with('success', 'Retur berhasil. Stok barang telah dikembalikan.');
    }

    public function printReceipt(Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        return view('store.print-receipt', compact('order'));
    }

    public function printReceiptAdmin(Order $order)
    {
        return view('store.print-receipt', compact('order'));
    }

    public function reviewStore(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $existing = Review::where('product_id', $product->id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            $existing->update([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'is_approved' => false,
            ]);
        } else {
            Review::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'is_approved' => false,
            ]);
        }

        return redirect()->back()->with('success', 'Ulasan berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function wishlistToggle(Product $product)
    {
        $wishlist = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->first();

        if ($wishlist) {
            $wishlist->delete();

            return response()->json(['status' => 'removed']);
        }

        Wishlist::create([
            'user_id' => auth()->id(),
            'product_id' => $product->id,
        ]);

        return response()->json(['status' => 'added']);
    }

    public function wishlistIndex()
    {
        $wishlists = auth()->user()->wishlistProducts()
            ->with('productImages')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->paginate(12);

        return view('store.wishlist', compact('wishlists'));
    }

    public function suggestions(Request $request)
    {
        $query = $request->get('q');

        if (! $query || strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::with('productImages')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('sku', 'like', '%'.$query.'%');
            })
            ->take(6)
            ->get();

        return response()->json($products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'price_formatted' => 'Rp'.number_format($p->price, 0, ',', '.'),
            'image' => $p->main_image,
        ]));
    }

    public function reorder(Request $request, Order $order)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $cart = collect(session('cart', []));

        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            if (! $product || ! $product->is_active || $product->stock < 1) {
                continue;
            }

            $existing = $cart->firstWhere('product_id', $product->id);
            if ($existing) {
                $cart = $cart->map(function ($ci) use ($product, $item) {
                    if ($ci['product_id'] === $product->id) {
                        $ci['quantity'] = min($ci['quantity'] + $item->quantity, $product->stock);
                    }

                    return $ci;
                });
            } else {
                $cart->push([
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'image' => $product->main_image,
                    'quantity' => min($item->quantity, $product->stock),
                    'stock' => $product->stock,
                ]);
            }
        }

        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Produk dari pesanan #'.$order->order_number.' berhasil ditambahkan ke keranjang!');
    }

    public function applyCoupon(Request $request)
    {
        $code = $request->get('code');
        $subtotal = (float) $request->get('subtotal', 0);

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return response()->json(['valid' => false, 'message' => 'Kode kupon tidak ditemukan.']);
        }

        if (! $coupon->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Kupon sudah tidak berlaku.']);
        }

        if (! $coupon->canUseBy(auth()->user())) {
            return response()->json(['valid' => false, 'message' => 'Kupon sudah mencapai batas pemakaian.']);
        }

        if ($subtotal < $coupon->min_order) {
            return response()->json(['valid' => false, 'message' => 'Minimal belanja Rp '.number_format($coupon->min_order, 0, ',', '.').' untuk menggunakan kupon ini.']);
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return response()->json([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'discount_formatted' => 'Rp '.number_format($discount, 0, ',', '.'),
            'message' => 'Kupon berhasil diterapkan! Potongan Rp '.number_format($discount, 0, ',', '.'),
        ]);
    }

    public function compareToggle(Product $product)
    {
        $compare = session('compare', collect());

        if ($compare->has($product->id)) {
            $compare->forget($product->id);
            $message = 'dihapus dari';
        } else {
            if ($compare->count() >= 4) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Maksimal 4 produk untuk dibandingkan']);
                }

                return back()->with('error', 'Maksimal 4 produk untuk dibandingkan');
            }
            $compare->put($product->id, [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'compare_price' => $product->compare_price,
                'image' => $product->main_image,
                'stock' => $product->stock,
                'sku' => $product->sku,
                'weight' => $product->weight,
                'rating' => $product->approvedReviews()->avg('rating'),
                'review_count' => $product->approvedReviews()->count(),
                'description' => $product->description,
                'category' => $product->category?->name,
                'attributes' => $product->attributes->groupBy('type')->map(fn ($items, $type) => $items->pluck('label')->implode(', ')),
            ]);
            $message = 'ditambahkan ke';
        }

        session(['compare' => $compare]);

        $count = $compare->count();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Produk {$message} perbandingan",
                'count' => $count,
            ]);
        }

        return back();
    }

    public function compareIndex()
    {
        $products = session('compare', collect());

        return view('store.compare', compact('products'));
    }

    protected function getCartWeight($cart): int
    {
        $productIds = $cart->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $totalWeight = 0;

        foreach ($cart as $item) {
            $product = $products->get($item['product_id']);
            $totalWeight += ($product?->weight ?? 200) * $item['quantity'];
        }

        return (int) ($totalWeight > 0 ? $totalWeight : 1000);
    }

    public function downloadDigital(Order $order, Product $product)
    {
        if ($order->user_id !== auth()->id()) {
            abort(403);
        }

        $orderItem = $order->items()->where('product_id', $product->id)->first();
        if (! $orderItem) {
            abort(404, 'Produk tidak ditemukan di pesanan ini');
        }

        if (! $product->is_digital || ! $product->digital_file) {
            abort(404, 'Produk ini bukan produk digital');
        }

        if ($order->payment_status !== 'paid') {
            return back()->with('error', 'Pesanan belum dibayar');
        }

        $download = OrderDownload::firstOrCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'user_id' => auth()->id(),
        ]);

        if (! $download->canDownload()) {
            return back()->with('error', 'Batas download habis (maks 5 kali)');
        }

        $download->recordDownload();

        if (! Storage::disk('public')->exists($product->digital_file)) {
            return back()->with('error', 'File tidak ditemukan');
        }

        return Storage::disk('public')->download($product->digital_file, $product->sku.'-'.$product->slug.'.'.pathinfo($product->digital_file, PATHINFO_EXTENSION));
    }
}
