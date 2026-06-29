<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        $todayProductsSold = OrderItem::whereHas('order', function ($q) use ($today) {
            $q->whereDate('created_at', $today)->where('payment_status', 'paid');
        })->sum('quantity');

        $monthRevenue = Order::whereMonth('created_at', $thisMonth)
            ->whereYear('created_at', $thisYear)
            ->where('payment_status', 'paid')
            ->sum('total');
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

        $totalStock = Product::sum('stock');
        $inventoryValue = Product::selectRaw('COALESCE(SUM(stock * price), 0) as total')->first()->total ?? 0;
        $lowStock = Product::where('stock', '<=', 5)->count();
        $totalProducts = Product::count();
        $pendingOrders = Order::whereIn('status', ['pending', 'processing'])->count();

        return [
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

            Stat::make('Pendapatan Bulan Ini', 'Rp '.number_format($monthRevenue, 0, ',', '.'))
                ->description('Pembayaran lunas bulan '.now()->format('F'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Customer Baru (Bulan Ini)', $newCustomersMonth)
                ->description('Total customer baru')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Pesanan Menunggu', $pendingOrders)
                ->description('Status pending + processing')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger')
                ->url(OrderResource::getUrl('index', ['tableFilters[menunggu][isActive]' => true])),

            Stat::make('Rata-rata Pesanan (AOV)', 'Rp '.number_format($aov, 0, ',', '.'))
                ->description('Nilai rata-rata per pesanan')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),

            Stat::make('Repeat Customer', $repeatCustomers)
                ->description('Customer dengan > 1 pesanan')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('success'),

            Stat::make('Conversion Rate', $conversionRate.'%')
                ->description('Pesanan per customer')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info'),

            Stat::make('Total Stok Gudang', number_format($totalStock, 0, ',', '.'))
                ->description('Total barang di gudang')
                ->descriptionIcon('heroicon-o-building-storefront')
                ->color('primary'),

            Stat::make('Nilai Inventory', 'Rp '.number_format($inventoryValue, 0, ',', '.'))
                ->description('Total nilai barang')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('warning'),

            Stat::make('Stok Menipis', $lowStock.' dari '.$totalProducts)
                ->description('Produk dengan stok <= 5')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'gray')
                ->url(ProductResource::getUrl('index', ['tableFilters[stock][value]' => 'low'])),
        ];
    }
}
