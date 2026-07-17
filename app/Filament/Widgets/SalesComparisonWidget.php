<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesComparisonWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Perbandingan Penjualan';

    protected function getStats(): array
    {
        $now = now();

        $thisMonthStart = $now->copy()->startOfMonth();
        $thisMonthEnd = $now->copy()->endOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $paid = fn ($start, $end) => Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end]);

        $thisRevenue = (clone $paid($thisMonthStart, $thisMonthEnd))->sum('total');
        $lastRevenue = (clone $paid($lastMonthStart, $lastMonthEnd))->sum('total');

        $thisOrders = (clone $paid($thisMonthStart, $thisMonthEnd))->count();
        $lastOrders = (clone $paid($lastMonthStart, $lastMonthEnd))->count();

        $thisCOGS = OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd]))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as cogs')
            ->value('cogs');
        $lastCOGS = OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'paid')->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as cogs')
            ->value('cogs');

        $thisExpenses = Expense::whereBetween('date', [$thisMonthStart->toDateString(), $thisMonthEnd->toDateString()])->sum('amount');
        $lastExpenses = Expense::whereBetween('date', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->sum('amount');

        $thisProfit = $thisRevenue - $thisCOGS - $thisExpenses;
        $lastProfit = $lastRevenue - $lastCOGS - $lastExpenses;

        $thisAOV = $thisOrders > 0 ? round($thisRevenue / $thisOrders) : 0;
        $lastAOV = $lastOrders > 0 ? round($lastRevenue / $lastOrders) : 0;

        return [
            Stat::make(
                'Pendapatan',
                'Rp '.number_format($thisRevenue, 0, ',', '.'),
            )
                ->description($this->comparisonLabel($thisRevenue, $lastRevenue, 'Rp '.number_format($lastRevenue, 0, ',', '.')))
                ->descriptionIcon($thisRevenue >= $lastRevenue ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($thisRevenue >= $lastRevenue ? 'success' : 'danger'),

            Stat::make(
                'Pesanan Lunas',
                number_format($thisOrders).' order',
            )
                ->description($this->comparisonLabel($thisOrders, $lastOrders, number_format($lastOrders).' order'))
                ->descriptionIcon($thisOrders >= $lastOrders ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($thisOrders >= $lastOrders ? 'success' : 'danger'),

            Stat::make(
                'Laba Bersih',
                'Rp '.number_format($thisProfit, 0, ',', '.'),
            )
                ->description($this->comparisonLabel($thisProfit, $lastProfit, 'Rp '.number_format($lastProfit, 0, ',', '.')))
                ->descriptionIcon($thisProfit >= $lastProfit ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($thisProfit >= $lastProfit ? 'success' : 'danger'),

            Stat::make(
                'AOV',
                'Rp '.number_format($thisAOV, 0, ',', '.'),
            )
                ->description($this->comparisonLabel($thisAOV, $lastAOV, 'Rp '.number_format($lastAOV, 0, ',', '.')))
                ->descriptionIcon($thisAOV >= $lastAOV ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($thisAOV >= $lastAOV ? 'success' : 'danger'),
        ];
    }

    private function comparisonLabel(float $current, float $previous, string $previousFormatted): string
    {
        if ($previous == 0) {
            return $current > 0 ? 'Baru bulan ini' : 'Tidak ada perubahan';
        }

        $change = round((($current - $previous) / abs($previous)) * 100, 1);
        $arrow = $change >= 0 ? '+' : '';

        return $arrow.number_format($change, 1).'% dari lalu ('.$previousFormatted.')';
    }
}
