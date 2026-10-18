<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Sumber tunggal angka laporan penjualan.
 *
 * Dipakai halaman Filament `Reports` DAN export CSV (`ReportController::export`).
 * Kalau perhitungannya ditulis dua kali, CSV dan layar pasti melenceng cepat
 * atau lambat — dan export yang isinya bukan yang dilihat admin akan dianggap
 * rusak (pernah terjadi: CSV-nya cuma daftar order mentah).
 */
class SalesReportService
{
    /**
     * @return array{
     *     sales: array<string, int|float>,
     *     profit: array<string, int|float>,
     *     topProducts: array<int, array{name: string, qty: int, revenue: float}>,
     *     topCategories: array<int, array{name: string, qty: int}>,
     *     orders: Collection<int, Order>
     * }
     */
    public function build(CarbonInterface $start, CarbonInterface $end): array
    {
        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->orderBy('created_at')
            ->get();

        // Order yang dihitung: lunas dan di dalam rentang. Semua angka turunan
        // (produk terjual, HPP, terlaris) WAJIB memakai filter yang sama,
        // kalau tidak "pendapatan" dan "HPP" datang dari populasi berbeda.
        $paidItems = fn () => OrderItem::whereHas(
            'order',
            fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')
        );

        $totalRevenue = (float) $orders->sum('total');
        $totalOrders = $orders->count();

        $sales = [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'total_products' => (int) $paidItems()->sum('quantity'),
            'average_order' => $totalOrders > 0 ? (int) round($totalRevenue / $totalOrders) : 0,
        ];

        $totalExpense = (float) Expense::whereBetween('expense_date', [$start, $end])->sum('amount');

        $totalCOGS = (float) $paidItems()
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as total_cogs')
            ->value('total_cogs');

        $profit = [
            'revenue' => $totalRevenue,
            'expense' => $totalExpense,
            'cogs' => $totalCOGS,
            'profit' => $totalRevenue - $totalCOGS - $totalExpense,
        ];

        $topProducts = $paidItems()
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(20)
            ->get()
            ->map(function ($item) {
                $product = Product::find($item->product_id);

                return [
                    'name' => $product?->name ?? 'Produk #'.$item->product_id,
                    'qty' => (int) $item->total_qty,
                    'revenue' => (float) $item->total_revenue,
                ];
            })
            ->all();

        $topCategories = $paidItems()
            ->selectRaw('products.category_id, SUM(order_items.quantity) as total_qty')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->groupBy('products.category_id')
            ->orderByDesc('total_qty')
            ->take(20)
            ->get()
            ->map(function ($item) {
                $category = Category::find($item->category_id);

                return [
                    'name' => $category?->name ?? 'Kategori #'.$item->category_id,
                    'qty' => (int) $item->total_qty,
                ];
            })
            ->all();

        return [
            'sales' => $sales,
            'profit' => $profit,
            'topProducts' => $topProducts,
            'topCategories' => $topCategories,
            'orders' => $orders,
        ];
    }
}
