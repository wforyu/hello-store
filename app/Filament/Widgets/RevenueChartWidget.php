<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 6;

    protected ?string $heading = 'Pendapatan 30 Hari Terakhir';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(function ($i) {
            $date = now()->subDays($i);
            $revenue = Order::whereDate('created_at', $date)
                ->where('payment_status', 'paid')
                ->sum('total');

            return [
                'date' => $date->format('d M'),
                'revenue' => (float) $revenue,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $days->pluck('revenue')->toArray(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
