<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDownload;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\ProductView;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Wishlist;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function home()
    {
        $showSliders = Setting::get('show_sliders', '1') === '1';
        $showCategories = Setting::get('show_categories', '1') === '1';
        $showFeatured = Setting::get('show_featured', '1') === '1';
        $showLatest = Setting::get('show_latest', '1') === '1';
        $showFlashSale = Setting::get('show_flash_sale', '1') === '1';
        $showBrands = Setting::get('show_brands', '1') === '1';

        $categories = $showCategories
            ? Category::whereNull('parent_id')->with('children')->get()
            : collect();
        $featuredProducts = $showFeatured
            ? Product::with('productImages', 'brand')->where('featured', true)->where('is_active', true)
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->withSum(['orderItems' => fn ($q) => $q->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))], 'quantity')
                ->latest()->take(8)->get()
            : collect();
        $latestProducts = $showLatest
            ? Product::with('productImages', 'brand')->where('is_active', true)
                ->withCount('approvedReviews')
                ->withAvg('approvedReviews', 'rating')
                ->withSum(['orderItems' => fn ($q) => $q->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))], 'quantity')
                ->latest()->take(8)->get()
            : collect();

        $activeFlashSale = $showFlashSale
            ? FlashSale::active()->with(['products.brand', 'products.productImages'])->first()
            : null;
        $flashSaleMap = $activeFlashSale ? $this->getFlashSaleMap($activeFlashSale) : collect();

        $sliders = $showSliders ? Slider::active()->get() : collect();

        $brands = $showBrands ? Brand::where('is_active', true)->orderBy('name')->get() : collect();

        return view('store.home', compact(
            'categories', 'featuredProducts', 'latestProducts',
            'activeFlashSale', 'flashSaleMap', 'sliders', 'brands',
            'showSliders', 'showCategories', 'showFeatured',
            'showLatest', 'showFlashSale', 'showBrands'
        ));
    }

    public function products(Request $request)
    {
        $query = Product::with('productImages', 'brand')->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->withSum(['orderItems' => fn ($q) => $q->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))], 'quantity');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%')
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

        if ($request->filled('brand')) {
            $brand = Brand::where('slug', $request->brand)->first();
            if ($brand) {
                $query->where('brand_id', $brand->id);
            }
        }

        if ($request->filled('flash_sale')) {
            $flashSale = FlashSale::find($request->flash_sale);
            if ($flashSale) {
                $flashProductIds = $flashSale->products()->pluck('products.id');
                $query->whereIn('products.id', $flashProductIds);
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
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        $activeFlashSale = FlashSale::active()->with(['products.brand', 'products.productImages'])->first();
        $flashSaleMap = $this->getFlashSaleMap($activeFlashSale);

        return view('store.products', compact('products', 'categories', 'brands', 'flashSaleMap'));
    }

    public function productDetail($slug)
    {
        $product = Product::with(['productImages', 'variants', 'brand'])->where('slug', $slug)->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->firstOrFail();

        ProductView::recordView($product, auth()->id());

        $relatedProducts = Product::with('productImages', 'brand')->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->withSum(['orderItems' => fn ($q) => $q->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))], 'quantity')
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

        $activeFlashSale = FlashSale::active()->with(['products.brand', 'products.productImages'])->first();
        $flashSaleMap = $this->getFlashSaleMap($activeFlashSale);

        $recentIds = collect(session('recently_viewed', []))
            ->filter(fn ($id) => $id !== $product->id)
            ->take(8)
            ->toArray();
        $recentProducts = ! empty($recentIds) ? Product::with('productImages', 'brand')
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->withSum(['orderItems' => fn ($q) => $q->whereHas('order', fn ($q) => $q->whereIn('status', ['delivered', 'completed']))], 'quantity')
            ->whereIn('id', $recentIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($p) => array_search($p->id, $recentIds))
            ->values() : collect();

        return view('store.product-detail', compact('product', 'relatedProducts', 'reviews', 'userReview', 'flashSaleMap', 'activeFlashSale', 'recentProducts'));
    }

    public function cartIndex()
    {
        $cart = collect(session('cart', []));

        return view('store.cart', compact('cart'));
    }

    public function cartCount()
    {
        $count = collect(session('cart', []))->sum('quantity');

        return response()->json(['count' => $count]);
    }

    public function cartAdd(Request $request, Product $product)
    {
        $variantId = $request->integer('variant_id');
        $qty = (int) ($request->quantity ?? 1);
        $variant = null;

        if ($variantId) {
            $variant = $product->variants()->where('is_active', true)->find($variantId);
            if (! $variant) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Varian tidak ditemukan!'], 422);
                }

                return redirect()->back()->with('error', 'Varian tidak ditemukan!');
            }

            if ($variant->stock < 1) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Stok varian habis!'], 422);
                }

                return redirect()->back()->with('error', 'Stok varian habis!');
            }

            $effectiveStock = $variant->stock;
            $price = $variant->price ?? $product->price;
            $weight = $variant->weight ?? $product->weight;
            $image = $variant->image
                ? (str_starts_with($variant->image, 'http') ? $variant->image : Storage::url($variant->image))
                : $product->main_image;
        } else {
            if ($product->stock < 1) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Stok produk habis!'], 422);
                }

                return redirect()->back()->with('error', 'Stok produk habis!');
            }

            $effectiveStock = $product->stock;
            $price = $product->price;
            $weight = $product->weight;
            $image = $product->main_image;
        }

        $cart = collect(session('cart', []));
        $key = $variantId ? "{$product->id}_v{$variantId}" : (string) $product->id;

        // Check for existing item with same key
        $existingIndex = $cart->search(fn ($item) => ($item['key'] ?? $item['product_id']) == $key);

        if ($existingIndex !== false) {
            $cart = $cart->map(function ($item, $index) use ($qty, $effectiveStock, $existingIndex) {
                if ($index === $existingIndex) {
                    $item['quantity'] = min($item['quantity'] + $qty, $effectiveStock);
                }

                return $item;
            });
        } else {
            $cart->push([
                'key' => $key,
                'product_id' => $product->id,
                'variant_id' => $variantId ?: null,
                'name' => $product->name,
                'variant_name' => $variant?->name,
                'slug' => $product->slug,
                'price' => (float) $price,
                'image' => $image,
                'quantity' => min($qty, $effectiveStock),
                'stock' => $effectiveStock,
            ]);
        }

        session(['cart' => $cart]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'cart' => $cart->values()->all(),
                'total' => $cart->sum(fn ($i) => $i['price'] * $i['quantity']),
                'count' => $cart->sum('quantity'),
            ]);
        }

        // Buy Now: add to cart then redirect to checkout
        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout');
        }

        return redirect()->back()->with('success', 'Produk ditambahkan ke keranjang!');
    }

    public function cartUpdate(Request $request)
    {
        $cart = collect(session('cart', []));

        $variantsToLoad = [];
        foreach ($cart as $item) {
            if (! empty($item['variant_id'])) {
                $variantsToLoad[] = $item['variant_id'];
            }
        }

        $liveVariants = ! empty($variantsToLoad)
            ? ProductVariant::whereIn('id', $variantsToLoad)->get()->keyBy('id')
            : collect();

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $cart = $cart->map(function ($item) use ($request, $liveProducts, $liveVariants) {
            $key = $item['key'] ?? $item['product_id'];

            if ($request->has("quantity_{$key}")) {
                if (! empty($item['variant_id'])) {
                    $variant = $liveVariants->get($item['variant_id']);
                    $liveStock = $variant ? $variant->stock : 0;
                    $livePrice = $variant && $variant->price !== null ? $variant->price : ($liveProducts->get($item['product_id'])?->price ?? $item['price']);
                } else {
                    $liveStock = $liveProducts->get($item['product_id'])?->stock ?? $item['stock'];
                    $livePrice = $liveProducts->get($item['product_id'])?->price ?? $item['price'];
                }

                $item['quantity'] = max(1, min($liveStock, (int) $request->input("quantity_{$key}")));
                $item['stock'] = $liveStock;
                $item['price'] = (float) $livePrice;
            }

            return $item;
        });

        session(['cart' => $cart]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'cart' => $cart->values()->all(),
                'total' => $cart->sum(fn ($i) => $i['price'] * $i['quantity']),
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Keranjang diperbarui!');
    }

    public function cartRemove($key)
    {
        $cart = collect(session('cart', []));
        $cart = $cart->reject(fn ($item) => ($item['key'] ?? $item['product_id']) == $key)->values();
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
            'use_points' => 'nullable|integer|min:0',
        ]);

        $subtotal = $cart->sum(fn ($item) => $item['price'] * $item['quantity']);
        $shippingCost = (float) $validated['shipping_cost'];

        $usePoints = min((int) ($validated['use_points'] ?? 0), auth()->user()->points);
        $pointDiscount = 0;
        if ($usePoints > 0) {
            $maxPointDiscount = (int) floor(($subtotal + $shippingCost) * 0.5);
            $usePoints = min($usePoints, $maxPointDiscount);
            $pointDiscount = $usePoints;
        }

        $address = Address::where('id', $validated['address_id'])
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variantIds = $cart->pluck('variant_id')->filter();
        $liveVariants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        foreach ($cart as $item) {
            if (! empty($item['variant_id'])) {
                $variant = $liveVariants->get($item['variant_id']);
                if (! $variant || $variant->stock < $item['quantity']) {
                    $availableStock = $variant ? $variant->stock : 0;

                    return redirect()->route('cart.index')->with('error', "Stok varian '{$item['name']}' tidak mencukupi (tersedia: {$availableStock})!");
                }
            } else {
                $product = $liveProducts->get($item['product_id']);
                if (! $product || $product->stock < $item['quantity']) {
                    $availableStock = $product ? $product->stock : 0;

                    return redirect()->route('cart.index')->with('error', "Stok '{$item['name']}' tidak mencukupi (tersedia: {$availableStock})!");
                }
            }
        }

        $discountAmount = 0;
        $coupon = null;
        if ($request->filled('coupon_code')) {
            $coupon = Coupon::where('code', $request->coupon_code)->first();
            if ($coupon && $coupon->canUseBy(auth()->user())) {
                $discountAmount = $coupon->calculateDiscount($subtotal);
            }
        }

        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $ppnBase = max(0, $subtotal - $discountAmount);
        $ppnAmount = $ppnEnabled ? round($ppnBase * $ppnRate / 100) : 0;

        $memberDiscountRate = auth()->user()->getSegmentDiscountRate();
        $memberDiscount = $memberDiscountRate > 0 ? (int) round($ppnBase * $memberDiscountRate) : 0;

        $total = $ppnBase + $shippingCost + $ppnAmount - $pointDiscount - $memberDiscount;

        $order = DB::transaction(function () use ($cart, $subtotal, $shippingCost, $ppnAmount, $ppnRate, $total, $validated, $address, $liveProducts, $liveVariants, $coupon, $discountAmount, $usePoints, $pointDiscount) {
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
                'notes' => ($validated['notes'] ?? '').($ppnAmount > 0 ? ' | PPN '.$ppnRate.'%: Rp '.number_format($ppnAmount, 0, ',', '.') : '').($pointDiscount > 0 ? ' | Poin: Rp '.number_format($pointDiscount, 0, ',', '.') : '').($memberDiscount > 0 ? ' | Diskon Member '.strtoupper(auth()->user()->segment).': -Rp '.number_format($memberDiscount, 0, ',', '.') : ''),
                'address_id' => $address->id,
                'coupon_id' => $coupon?->id,
                'discount' => $discountAmount,
            ]);

            foreach ($cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => ! empty($item['variant_id']) ? $item['variant_id'] : null,
                    'product_name' => ! empty($item['variant_name'])
                        ? $item['name'].' - '.$item['variant_name']
                        : $item['name'],
                    'product_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);

                if (! empty($item['variant_id'])) {
                    $v = $liveVariants->get($item['variant_id']);
                    if ($v) {
                        $v->decrement('stock', $item['quantity']);
                        $p = $liveProducts->get($item['product_id']);
                        if ($p) {
                            $p->recordStockHistory(-$item['quantity'], 'order', 'Varian: '.$v->name, Order::class, $order->id);
                        }
                    }
                } else {
                    $p = $liveProducts->get($item['product_id']);
                    if ($p) {
                        $p->decrement('stock', $item['quantity']);
                        $p->recordStockHistory(-$item['quantity'], 'order', null, Order::class, $order->id);
                    }
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

            if ($usePoints > 0) {
                auth()->user()->redeemPoints($usePoints, 'Poin ditukar untuk pesanan #'.$order->order_number, $order);
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
            '/admin/resources/orders/'.$order->id.'/edit'
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

        $order->load(['items.variant', 'payment', 'address']);

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
            $order->load(['items.product', 'items.variant']);

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'payment_status' => 'unpaid',
            ]);

            foreach ($order->items as $item) {
                if ($item->product_variant_id && $item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                    if ($item->product) {
                        $item->product->recordStockHistory(
                            $item->quantity,
                            'order',
                            'Varian: '.$item->variant->name.' | Pembatalan #'.$order->order_number,
                            Order::class,
                            $order->id
                        );
                    }
                } elseif ($item->product) {
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
            $order->load(['items.product', 'items.variant']);

            foreach ($order->items as $item) {
                if ($item->product_variant_id && $item->variant) {
                    $item->variant->increment('stock', $item->quantity);
                    if ($item->product) {
                        $item->product->recordStockHistory(
                            $item->quantity,
                            'refund',
                            'Varian: '.$item->variant->name.' | Retur #'.$order->order_number,
                            Order::class,
                            $order->id
                        );
                    }
                } elseif ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                    $item->product->recordStockHistory(
                        $item->quantity,
                        'refund',
                        'Retur pesanan #'.$order->order_number,
                        Order::class,
                        $order->id
                    );
                }
            }

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
                'comment' => $validated['comment'] ?? null,
                'is_approved' => false,
            ]);
        } else {
            Review::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
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

        $variantIds = $order->items->whereNotNull('product_variant_id')->pluck('product_variant_id');
        $variants = ProductVariant::with('product')->whereIn('id', $variantIds)->get()->keyBy('id');

        $productIds = $order->items->whereNull('product_variant_id')->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                $variant = $variants->get($item->product_variant_id);
                if (! $variant || ! $variant->is_active || ! $variant->product?->is_active || $variant->stock < 1) {
                    continue;
                }

                $product = $variant->product;
                $key = $product->id.'_v'.$variant->id;
                $existingIndex = $cart->search(fn ($ci) => ($ci['key'] ?? $ci['product_id']) == $key);

                if ($existingIndex !== false) {
                    $cart = $cart->map(function ($ci, $i) use ($item, $variant, $existingIndex) {
                        if ($i === $existingIndex) {
                            $ci['quantity'] = min($ci['quantity'] + $item->quantity, $variant->stock);
                        }

                        return $ci;
                    });
                } else {
                    $cart->push([
                        'key' => $key,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'name' => $product->name,
                        'variant_name' => $variant->name,
                        'slug' => $product->slug,
                        'price' => (float) ($variant->price ?? $product->price),
                        'image' => $variant->image
                            ? (str_starts_with($variant->image, 'http') ? $variant->image : Storage::url($variant->image))
                            : $product->main_image,
                        'quantity' => min($item->quantity, $variant->stock),
                        'stock' => $variant->stock,
                    ]);
                }
            } else {
                $product = $products->get($item->product_id);
                if (! $product || ! $product->is_active || $product->stock < 1) {
                    continue;
                }

                $key = (string) $product->id;
                $existingIndex = $cart->search(fn ($ci) => ($ci['key'] ?? $ci['product_id']) == $key);

                if ($existingIndex !== false) {
                    $cart = $cart->map(function ($ci, $i) use ($product, $item, $existingIndex) {
                        if ($i === $existingIndex) {
                            $ci['quantity'] = min($ci['quantity'] + $item->quantity, $product->stock);
                        }

                        return $ci;
                    });
                } else {
                    $cart->push([
                        'key' => $key,
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'name' => $product->name,
                        'variant_name' => null,
                        'slug' => $product->slug,
                        'price' => (float) $product->price,
                        'image' => $product->main_image,
                        'quantity' => min($item->quantity, $product->stock),
                        'stock' => $product->stock,
                    ]);
                }
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
        $product->load('approvedReviews', 'attributes', 'category');
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

    protected function getFlashSaleMap(?FlashSale $activeFlashSale): Collection
    {
        if (! $activeFlashSale) {
            return collect();
        }

        $map = [];
        foreach ($activeFlashSale->products as $product) {
            $pivot = $product->pivot;
            $price = (float) $product->price;
            if ($pivot->discount_type === 'percentage') {
                $flashPrice = max(0, $price - ($price * $pivot->discount_value / 100));
            } else {
                $flashPrice = max(0, $price - (float) $pivot->discount_value);
            }
            $map[$product->id] = [
                'flash_sale_id' => $activeFlashSale->id,
                'flash_sale_name' => $activeFlashSale->name,
                'discount_type' => $pivot->discount_type,
                'discount_value' => $pivot->discount_value,
                'flash_price' => round($flashPrice),
                'max_qty' => $pivot->max_qty ?? 0,
                'sold_count' => $pivot->sold_count ?? 0,
            ];
        }

        return collect($map);
    }

    public function bundles()
    {
        $bundles = ProductBundle::with(['products.productImages'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_time')->orWhere('start_time', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>=', now()))
            ->latest()
            ->paginate(12);

        return view('store.bundles', compact('bundles'));
    }

    public function cartAddBundle(Request $request, ProductBundle $bundle)
    {
        $bundle->load('products');

        if ($bundle->products->isEmpty()) {
            return redirect()->back()->with('error', 'Paket ini tidak memiliki produk!');
        }

        // Calculate discount percentage of bundle vs original total
        $originalTotal = (float) $bundle->total_original_price;
        $bundlePrice = (float) $bundle->bundle_price;
        $discountPercent = $originalTotal > 0 ? ($originalTotal - $bundlePrice) / $originalTotal : 0;

        $cart = collect(session('cart', []));

        foreach ($bundle->products as $product) {
            if (! $product->is_active) {
                continue;
            }

            $qty = (int) ($product->pivot->quantity ?? 1);
            $unitPrice = $product->price;

            // Apply proportional discount to each item
            $itemPrice = $discountPercent > 0
                ? round($unitPrice * (1 - $discountPercent))
                : (float) $unitPrice;

            $effectiveStock = $product->stock;
            if ($effectiveStock < 1) {
                return redirect()->back()->with('error', "Stok '{$product->name}' dalam paket ini habis!");
            }

            $key = "{$product->id}_bundle{$bundle->id}";

            $existingIndex = $cart->search(fn ($item) => ($item['key'] ?? $item['product_id']) == $key);

            if ($existingIndex !== false) {
                $cart = $cart->map(function ($item, $index) use ($qty, $effectiveStock, $existingIndex) {
                    if ($index === $existingIndex) {
                        $item['quantity'] = min($item['quantity'] + $qty, $effectiveStock);
                    }

                    return $item;
                });
            } else {
                $cart->push([
                    'key' => $key,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $itemPrice,
                    'image' => $product->main_image,
                    'quantity' => min($qty, $effectiveStock),
                    'stock' => $effectiveStock,
                    'bundle_id' => $bundle->id,
                    'bundle_name' => $bundle->name,
                ]);
            }
        }

        session(['cart' => $cart]);

        return redirect()->back()->with('success', "Paket '{$bundle->name}' ditambahkan ke keranjang!");
    }

    protected function getCartWeight($cart): int
    {
        $productIds = $cart->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variantIds = $cart->pluck('variant_id')->filter();
        $variants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();
        $totalWeight = 0;

        foreach ($cart as $item) {
            if (! empty($item['variant_id'])) {
                $variant = $variants->get($item['variant_id']);
                $totalWeight += ($variant?->weight ?? ($products->get($item['product_id'])?->weight ?? 200)) * $item['quantity'];
            } else {
                $product = $products->get($item['product_id']);
                $totalWeight += ($product?->weight ?? 200) * $item['quantity'];
            }
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
