<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total');
        $totalExpenses = Expense::sum('amount');
        $totalCOGS = OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as total_cogs')
            ->value('total_cogs');
        $totalNetProfit = $totalRevenue - $totalCOGS - $totalExpenses;
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();

        return [
            Stat::make('Total Pendapatan', 'Rp'.number_format($totalRevenue, 0, ',', '.'))
                ->description('Dari pesanan lunas')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Total Pengeluaran', 'Rp'.number_format($totalExpenses, 0, ',', '.'))
                ->description('Semua biaya toko')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('danger'),

            Stat::make('Laba Bersih', 'Rp'.number_format($totalNetProfit, 0, ',', '.'))
                ->description('Revenue - COGS - Pengeluaran')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),

            Stat::make('Total Pesanan', (string) $totalOrders)
                ->description($pendingOrders.' menunggu proses')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('info'),
        ];
    }
}
