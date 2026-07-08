<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftExpense;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::with('productImages')->where('is_active', true)->latest()->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'stock' => $p->stock,
            'image' => $p->main_image,
            'category_id' => $p->category_id,
            'sku' => $p->sku,
        ])->values();
        $cart = collect(session('pos_cart', []));
        $categories = Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $customers = User::where('role', 'customer')->orderBy('name')->get(['id', 'name', 'email']);
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $activeShift = Shift::where('user_id', auth()->id())->whereNull('closed_at')->first();

        return view('pos.index', compact('products', 'cart', 'categories', 'customers', 'ppnRate', 'activeShift'));
    }

    public function search(Request $request)
    {
        $products = Product::with('productImages')->where('is_active', true)
            ->when($request->search, function ($q) use ($request) {
                $s = $request->search;
                $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', '%'.$s.'%')
                        ->orWhere('sku', 'like', '%'.$s.'%');
                });
            })
            ->when($request->category_id, function ($q) use ($request) {
                $categoryIds = Category::where('id', $request->category_id)
                    ->orWhere('parent_id', $request->category_id)
                    ->pluck('id');
                $q->whereIn('category_id', $categoryIds);
            })
            ->latest()
            ->get();

        return response()->json($products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->price,
            'stock' => $p->stock,
            'image' => $p->main_image,
            'category_id' => $p->category_id,
            'sku' => $p->sku,
        ]));
    }

    public function customers(Request $request)
    {
        $users = User::where('role', 'customer')
            ->when($request->search, fn ($q) => $q->where('name', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $product = Product::with('productImages')->findOrFail($request->product_id);

        if ($product->stock < 1) {
            return response()->json(['error' => 'Stok produk habis!'], 422);
        }

        $cart = collect(session('pos_cart', []));
        $existing = $cart->firstWhere('product_id', $product->id);
        $qty = max(1, (int) ($request->quantity ?? 1));

        if ($existing) {
            $cart = $cart->map(function ($item) use ($product, $qty) {
                if ($item['product_id'] === $product->id) {
                    $item['quantity'] = min($item['quantity'] + $qty, $product->stock);
                    $item['stock'] = $product->stock;
                }

                return $item;
            });
        } else {
            $cart->push([
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => min($qty, $product->stock),
                'stock' => $product->stock,
                'image' => $product->main_image,
                'sku' => $product->sku,
                'discount' => 0,
                'discount_type' => 'nominal',
            ]);
        }

        session(['pos_cart' => $cart]);

        return response()->json(['cart' => $cart, 'subtotal' => $this->getSubtotal($cart)]);
    }

    public function update(Request $request)
    {
        $cart = collect(session('pos_cart', []));

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $cart = $cart->map(function ($item) use ($request, $liveProducts) {
            if ($item['product_id'] === (int) $request->product_id) {
                $liveStock = $liveProducts->get($item['product_id'])?->stock ?? $item['stock'];

                if ($request->has('quantity')) {
                    $item['quantity'] = max(1, min($liveStock, (int) $request->quantity));
                }
                $item['stock'] = $liveStock;

                if ($request->has('discount')) {
                    $item['discount'] = (float) $request->discount;
                }
                if ($request->has('discount_type')) {
                    $item['discount_type'] = $request->discount_type === 'percent' ? 'percent' : 'nominal';
                }
            }

            return $item;
        });

        session(['pos_cart' => $cart]);

        return response()->json(['cart' => $cart, 'subtotal' => $this->getSubtotal($cart)]);
    }

    public function remove(Request $request)
    {
        $cart = collect(session('pos_cart', []))
            ->where('product_id', '!=', (int) $request->product_id)
            ->values();

        session(['pos_cart' => $cart]);

        return response()->json(['cart' => $cart, 'subtotal' => $this->getSubtotal($cart)]);
    }

    public function checkout(Request $request)
    {
        $cart = collect(session('pos_cart', []));
        if ($cart->isEmpty()) {
            return response()->json(['error' => 'Keranjang kosong!'], 422);
        }

        $itemSubtotal = $this->getSubtotal($cart);
        $itemDiscounts = $this->getItemDiscountsTotal($cart);
        $afterItemDiscount = $itemSubtotal - $itemDiscounts;

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'customer_id' => 'nullable|integer|exists:users,id',
            'amount_paid' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:'.$afterItemDiscount,
            'ppn' => 'boolean',
            'payment_method' => 'required|in:cash,qris,debit,transfer',
            'order_type' => 'required|in:dine_in,takeaway',
        ]);

        $globalDiscount = (float) ($validated['discount'] ?? 0);
        $ppnEnabled = Setting::get('ppn_enabled', '0') === '1';
        $ppn = $ppnEnabled && (bool) ($validated['ppn'] ?? false);
        $ppnRate = (int) Setting::get('ppn_percentage', 11);
        $ppnAmount = $ppn ? round(max(0, $afterItemDiscount - $globalDiscount) * $ppnRate / 100) : 0;
        $total = max(0, $afterItemDiscount - $globalDiscount + $ppnAmount);

        if ($validated['payment_method'] === 'cash') {
            if (! $request->filled('amount_paid') || $validated['amount_paid'] < $total) {
                return response()->json(['error' => 'Jumlah dibayar kurang dari total!'], 422);
            }
        }

        $productIds = $cart->pluck('product_id');
        $liveProducts = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($cart as $item) {
            $product = $liveProducts->get($item['product_id']);
            if (! $product || $product->stock < $item['quantity']) {
                return response()->json([
                    'error' => "Stok '{$item['name']}' tidak mencukupi (tersedia: ".($product?->stock ?? 0).')!',
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($cart, $itemSubtotal, $globalDiscount, $ppn, $ppnAmount, $ppnRate, $total, $validated, $liveProducts, $activeShift) {
            $paymentMethodMap = [
                'cash' => 'cash',
                'qris' => 'qris',
                'debit' => 'debit_card',
                'transfer' => 'bank_transfer',
            ];

            $orderTypeLabel = $validated['order_type'] === 'dine_in' ? 'Dine-in' : 'Takeaway';

            $notes = $orderTypeLabel.' - '.($validated['customer_name'] ?? 'Umum');
            if ($globalDiscount > 0) {
                $notes .= ' | Diskon: Rp '.number_format($globalDiscount, 0, ',', '.');
            }
            if ($ppn) {
                $notes .= ' | PPN '.$ppnRate.'%: Rp '.number_format($ppnAmount, 0, ',', '.');
            }
            if ($validated['customer_id']) {
                $notes .= ' | Customer ID: '.$validated['customer_id'];
            }

            $order = Order::create([
                'user_id' => auth()->id(),
                'order_number' => 'POS-'.strtoupper(Str::random(8)),
                'status' => 'completed',
                'subtotal' => $itemSubtotal,
                'shipping_cost' => 0,
                'total' => $total,
                'payment_method' => $paymentMethodMap[$validated['payment_method']],
                'payment_status' => 'paid',
                'notes' => $notes,
            ]);

            if ($activeShift) {
                $order->update(['shift_id' => $activeShift->id]);
            }

            foreach ($cart as $item) {
                $itemTotal = $item['price'] * $item['quantity'];
                $itemDisc = $this->calcItemDiscount($item);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['name'],
                    'product_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemTotal - $itemDisc,
                ]);

                $p = $liveProducts->get($item['product_id']);
                if ($p) {
                    $p->decrement('stock', $item['quantity']);
                    $p->recordStockHistory(-$item['quantity'], 'pos', null, Order::class, $order->id);
                }
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $paymentMethodMap[$validated['payment_method']],
                'amount' => $total,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return $order;
        });

        session()->forget('pos_cart');

        $change = $validated['payment_method'] === 'cash'
            ? max(0, ($validated['amount_paid'] ?? 0) - $total)
            : 0;

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'change' => $change,
            'discount' => $globalDiscount,
            'ppn' => $ppnAmount,
            'payment_method' => $validated['payment_method'],
        ]);
    }

    public function holdOrder(Request $request)
    {
        $cart = collect(session('pos_cart', []));
        if ($cart->isEmpty()) {
            return response()->json(['error' => 'Keranjang kosong!'], 422);
        }

        $holds = collect(session('pos_holds', []));
        $id = ($holds->max('id') ?? 0) + 1;

        $holds->push([
            'id' => $id,
            'name' => $request->name ?? 'Pesanan #'.$id,
            'cart' => $cart,
            'customer_name' => $request->customer_name ?? '',
            'created_at' => now()->format('H:i'),
        ]);

        session(['pos_holds' => $holds]);
        session()->forget('pos_cart');

        return response()->json(['holds' => $holds]);
    }

    public function recallOrders()
    {
        $holds = collect(session('pos_holds', []));

        return response()->json(['holds' => $holds]);
    }

    public function recallOrder($id)
    {
        $holds = collect(session('pos_holds', []));
        $hold = $holds->firstWhere('id', (int) $id);

        if (! $hold) {
            return response()->json(['error' => 'Pesanan tidak ditemukan!'], 404);
        }

        session(['pos_cart' => $hold['cart']]);

        $holds = $holds->where('id', '!=', (int) $id)->values();
        session(['pos_holds' => $holds]);

        return response()->json([
            'cart' => $hold['cart'],
            'customer_name' => $hold['customer_name'] ?? '',
            'holds' => $holds,
        ]);
    }

    public function deleteHold($id)
    {
        $holds = collect(session('pos_holds', []))
            ->where('id', '!=', (int) $id)
            ->values();

        session(['pos_holds' => $holds]);

        return response()->json(['holds' => $holds]);
    }

    public function history()
    {
        $orders = Order::whereDate('created_at', today())
            ->where('status', 'completed')
            ->latest()
            ->get(['id', 'order_number', 'total', 'payment_method', 'payment_status', 'notes', 'created_at']);

        return response()->json($orders->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'total' => (float) $o->total,
            'payment_method' => $o->payment_method,
            'payment_status' => $o->payment_status,
            'customer_name' => Str::of($o->notes)->after(' - ')->before(' | ')->toString() ?: ($o->notes ?: 'Umum'),
            'time' => $o->created_at->format('H:i'),
        ]));
    }

    public function scanBarcode(Request $request)
    {
        $request->validate(['barcode' => 'required|string|max:100']);

        $barcode = trim($request->barcode);

        $product = Product::with('productImages')->where('sku', $barcode)
            ->where('is_active', true)
            ->first();

        if (! $product) {
            return response()->json([
                'found' => false,
                'message' => 'Produk dengan kode "'.$barcode.'" tidak ditemukan!',
            ]);
        }

        if ($product->stock < 1) {
            return response()->json([
                'found' => false,
                'message' => 'Stok produk "'.$product->name.'" habis!',
            ]);
        }

        $cart = collect(session('pos_cart', []));
        $existing = $cart->firstWhere('product_id', $product->id);

        if ($existing) {
            $cart = $cart->map(function ($item) use ($product) {
                if ($item['product_id'] === $product->id) {
                    $item['quantity'] = min($item['quantity'] + 1, $product->stock);
                    $item['stock'] = $product->stock;
                }

                return $item;
            });
        } else {
            $cart->push([
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => 1,
                'stock' => $product->stock,
                'image' => $product->main_image,
                'sku' => $product->sku,
                'discount' => 0,
                'discount_type' => 'nominal',
            ]);
        }

        session(['pos_cart' => $cart]);

        return response()->json([
            'found' => true,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'price_formatted' => 'Rp '.number_format($product->price, 0, ',', '.'),
            ],
            'message' => 'Produk "'.$product->name.'" berhasil ditambahkan!',
            'cart' => $cart,
            'subtotal' => $this->getSubtotal($cart),
        ]);
    }

    public function openShift(Request $request)
    {
        $validated = $request->validate([
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        $activeShift = Shift::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if ($activeShift) {
            return redirect()->route('pos.index')->with('error', 'Kamu sudah memiliki shift yang aktif!');
        }

        Shift::create([
            'user_id' => auth()->id(),
            'opened_at' => now(),
            'opening_balance' => $validated['opening_balance'] ?? 0,
        ]);

        return redirect()->route('pos.index')->with('success', 'Shift berhasil dibuka!');
    }

    public function closeShift(Request $request)
    {
        $shift = Shift::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (! $shift) {
            return redirect()->route('pos.index')->with('error', 'Tidak ada shift yang aktif!');
        }

        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $totalRevenue = $shift->totalRevenue();
        $totalExpenses = (float) $shift->expenses()->sum('amount');
        $expectedBalance = (float) $shift->opening_balance + $totalRevenue - $totalExpenses;

        $shift->update([
            'closed_at' => now(),
            'closing_balance' => $validated['closing_balance'],
            'expected_balance' => $expectedBalance,
            'difference' => $validated['closing_balance'] - $expectedBalance,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('pos.index')->with('success', 'Shift berhasil ditutup!');
    }

    public function shiftHistory()
    {
        $shifts = Shift::with('user')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('pos.shifts', compact('shifts'));
    }

    public function addExpense(Request $request)
    {
        $shift = Shift::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (! $shift) {
            return redirect()->route('pos.index')->with('error', 'Tidak ada shift yang aktif!');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
        ]);

        ShiftExpense::create([
            'shift_id' => $shift->id,
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'category' => $validated['category'] ?? 'operasional',
        ]);

        return redirect()->route('pos.index')->with('success', 'Kas keluar berhasil dicatat');
    }

    public function deleteExpense(ShiftExpense $shiftExpense)
    {
        if ($shiftExpense->shift?->user_id !== auth()->id()) {
            abort(403);
        }

        $shiftExpense->delete();

        return redirect()->route('pos.index')->with('success', 'Catatan kas keluar dihapus');
    }

    public function printReceipt(Order $order)
    {
        return view('pos.print-receipt', compact('order'));
    }

    protected function getSubtotal($cart): float
    {
        return $cart->sum(fn ($item) => $item['price'] * $item['quantity']);
    }

    protected function getItemDiscountsTotal($cart): float
    {
        return $cart->sum(fn ($item) => $this->calcItemDiscount($item));
    }

    protected function calcItemDiscount(array $item): float
    {
        $itemTotal = $item['price'] * $item['quantity'];
        $discount = (float) ($item['discount'] ?? 0);
        $type = $item['discount_type'] ?? 'nominal';

        if ($discount <= 0) {
            return 0;
        }

        if ($type === 'percent') {
            return round($itemTotal * min($discount, 100) / 100);
        }

        return min($discount, $itemTotal);
    }
}
