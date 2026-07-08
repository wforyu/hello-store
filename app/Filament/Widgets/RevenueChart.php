<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 6;

    protected ?string $heading = 'Pendapatan Bulanan';

    protected function getData(): array
    {
        $months = collect();
        $revenues = collect();

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $total = Order::where('payment_status', 'paid')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('total');

            $months->push($date->format('M'));
            $revenues->push((float) $total);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $revenues->toArray(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $months->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
