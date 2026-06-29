<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class TopProductsWidget extends Widget
{
    protected string $view = 'filament.widgets.top-products-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function getTopProducts()
    {
        return OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(10)
            ->with('product')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->product?->name ?? 'Produk Dihapus',
                'qty' => $item->total_qty,
            ]);
    }

    public function getTopCategories()
    {
        return OrderItem::select('products.category_id', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->groupBy('products.category_id')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get()
            ->map(fn ($item) => [
                'name' => Category::find($item->category_id)?->name ?? 'Tanpa Kategori',
                'qty' => $item->total_qty,
            ]);
    }

    public function getTopCashiers()
    {
        return Order::select('user_id', DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total) as total_revenue'))
            ->where('payment_status', 'paid')
            ->whereIn('user_id', User::whereIn('role', ['admin', 'cashier'])->pluck('id'))
            ->groupBy('user_id')
            ->orderByDesc('total_orders')
            ->take(10)
            ->with('user')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->user?->name ?? 'User Dihapus',
                'orders' => $item->total_orders,
                'revenue' => $item->total_revenue,
            ]);
    }
}
