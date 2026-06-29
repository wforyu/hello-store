<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayOrders = Order::whereDate('created_at', today())->count();
        $monthRevenue = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('payment_status', 'paid')
            ->sum('total');
        $pendingOrders = Order::whereIn('status', ['pending', 'processing'])->count();
        $lowStock = Product::where('stock', '<=', 5)->count();
        $totalProducts = Product::count();

        return [
            Stat::make('Pesanan Hari Ini', $todayOrders)
                ->description('Total pesanan hari ini')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('warning')
                ->url(OrderResource::getUrl('index', ['tableFilters[hari_ini][isActive]' => true])),
            Stat::make('Pendapatan Bulan Ini', 'Rp '.number_format($monthRevenue, 0, ',', '.'))
                ->description('Pembayaran lunas bulan '.now()->format('F'))
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Pesanan Menunggu', $pendingOrders)
                ->description('Pending + Processing')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info')
                ->url(OrderResource::getUrl('index', ['tableFilters[menunggu][isActive]' => true])),
            Stat::make('Stok Menipis', $lowStock.' dari '.$totalProducts)
                ->description('Produk dengan stok ≤ 5')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'gray')
                ->url(ProductResource::getUrl('index', ['tableFilters[stock][value]' => 'low'])),
        ];
    }
}
