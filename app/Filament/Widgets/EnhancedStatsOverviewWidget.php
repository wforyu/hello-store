<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Expense;
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

        // Today's stats
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        $todayProductsSold = OrderItem::whereHas('order', function ($q) use ($today) {
            $q->whereDate('created_at', $today)->where('payment_status', 'paid');
        })->sum('quantity');
        $todayExpenses = Expense::whereDate('date', $today)->sum('amount');
        $netProfit = $todayRevenue - $todayExpenses;

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

        return [
            // Row 1 — Hari Ini
            Stat::make('🧾 Pesanan Hari Ini', $todayOrders)
                ->description('Total pesanan hari ini')
                ->descriptionIcon('heroicon-o-shopping-cart')
                ->color('warning')
                ->url(OrderResource::getUrl('index', ['tableFilters[hari_ini][isActive] => true]')),
            Stat::make('📦 Produk Terjual (Hari Ini)', $todayProductsSold)
                ->description('Total barang terjual hari ini')
                ->descriptionIcon('heroicon-o-cube')
                ->color('success'),
            Stat::make('💰 Pendapatan Hari Ini', 'Rp '.number_format($todayRevenue, 0, ',', '.'))
                ->description('Pembayaran lunas hari ini')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('📊 Laba Bersih Hari Ini', 'Rp '.number_format($netProfit, 0, ',', '.'))
                ->description($netProfit >= 0 ? 'Pendapatan - Biaya hari ini' : 'Defisit hari ini')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color($netProfit >= 0 ? 'success' : 'danger'),

            // Row 2 — Customers & Performance
            Stat::make('👤 Customer Baru (Bulan Ini)', $newCustomersMonth)
                ->description('Total customer baru')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('🔄 Repeat Customer', $repeatCustomers)
                ->description('Customer dengan > 1 pesanan')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color('success'),
            Stat::make('📈 Rata-rata Pesanan (AOV)', 'Rp '.number_format($aov, 0, ',', '.'))
                ->description('Nilai rata-rata per pesanan')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('warning'),
            Stat::make('🎯 Conversion Rate', $conversionRate.'%')
                ->description('Pesanan per customer')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info'),
        ];
    }
}
