<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EnhancedStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = today();
        $thisMonth = now()->month;
        $thisYear = now()->year;

        // Today's stats
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        $todayProductsSold = OrderItem::whereHas('order', function ($q) use ($today) {
            $q->whereDate('created_at', $today)->where('payment_status', 'paid');
        })->sum('quantity');
        $todayExpenses = Expense::whereDate('expense_date', $today)->sum('amount');
        $todayCOGS = OrderItem::whereHas('order', fn ($q) => $q->whereDate('created_at', $today)->where('payment_status', 'paid'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as total_cogs')
            ->value('total_cogs');
        $netProfit = $todayRevenue - $todayCOGS - $todayExpenses;

        // Month & customer stats
        $newCustomersMonth = User::where('role', 'customer')
            ->whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->count();

        $totalOrders = Order::count();
        $totalCustomers = User::where('role', 'customer')->count();
        $repeatCustomers = User::where('role', 'customer')
            ->has('orders', '>=', 2)
            ->count();

        $paidOrdersQuery = Order::where('payment_status', 'paid');
        $paidOrdersCount = (clone $paidOrdersQuery)->count();
        $paidOrdersTotal = (clone $paidOrdersQuery)->sum('total');
        $aov = $paidOrdersCount > 0 ? round($paidOrdersTotal / $paidOrdersCount) : 0;
        $conversionRate = $totalCustomers > 0 ? round(($totalOrders / $totalCustomers) * 100, 1) : 0;

        // Stock alerts
        $lowStockCount = Product::where('stock', '<=', 5)->where('stock', '>', 0)->count()
            + ProductVariant::where('stock', '<=', 5)->where('stock', '>', 0)->count();
        $outOfStockCount = Product::where('stock', 0)->count()
            + ProductVariant::where('stock', 0)->count();

        return [
            // Row 1 — Hari Ini
            Stat::make('Pesanan Hari Ini', $todayOrders)
                ->description('Total pesanan hari ini')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('warning')
                ->url(OrderResource::getUrl('index', ['tableFilters[hari_ini][isActive]' => true])),
            Stat::make('Produk Terjual (Hari Ini)', $todayProductsSold)
                ->description('Total barang terjual hari ini')
                ->descriptionIcon('heroicon-o-cube')
                ->color('success'),
            Stat::make('Pendapatan Hari Ini', 'Rp '.number_format($todayRevenue, 0, ',', '.'))
                ->description('Pembayaran lunas hari ini')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Laba Bersih Hari Ini', 'Rp '.number_format($netProfit, 0, ',', '.'))
                ->description($netProfit >= 0 ? 'Revenue - COGS - Biaya hari ini' : 'Defisit hari ini')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            Stat::make('Customer Baru (Bulan Ini)', $newCustomersMonth)
                ->description('Total customer baru')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Repeat Customer', $repeatCustomers)
                ->description('Customer dengan > 1 pesanan')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('success'),
            Stat::make('Rata-rata Pesanan (AOV)', 'Rp '.number_format($aov, 0, ',', '.'))
                ->description('Nilai rata-rata per pesanan')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),
            Stat::make('Conversion Rate', $conversionRate.'%')
                ->description('Pesanan per customer')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info'),

            // Row 3 — Stok
            Stat::make('Stok Menipis', $lowStockCount)
                ->description($lowStockCount.' produk dengan stok ≤ 5')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url(ProductResource::getUrl('index', ['tableFilters[stock][value]' => 'low'])),
            Stat::make('Stok Habis', $outOfStockCount)
                ->description($outOfStockCount.' produk dengan stok 0')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger')
                ->url(ProductResource::getUrl('index', ['tableFilters[stock][value]' => 'out'])),
        ];
    }
}
