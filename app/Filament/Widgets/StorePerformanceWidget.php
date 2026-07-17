<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StorePerformanceWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Performa Toko';

    protected function getStats(): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $totalOrders30d = Order::where('created_at', '>=', $thirtyDaysAgo)->count();
        $completedOrders30d = Order::where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', 'delivered')
            ->count();
        $cancelledOrders30d = Order::where('created_at', '>=', $thirtyDaysAgo)
            ->where('status', 'cancelled')
            ->count();

        $fulfillmentRate = $totalOrders30d > 0
            ? round(($completedOrders30d / $totalOrders30d) * 100, 1)
            : 0;

        $cancellationRate = $totalOrders30d > 0
            ? round(($cancelledOrders30d / $totalOrders30d) * 100, 1)
            : 0;

        $avgProcessTime = Order::where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('shipped_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, shipped_at)) as avg_hours')
            ->value('avg_hours');

        $avgProcessLabel = $avgProcessTime
            ? round($avgProcessTime).' jam'
            : '-';

        $avgShipTime = Order::where('created_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('shipped_at')
            ->whereNotNull('delivered_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, shipped_at, delivered_at)) as avg_hours')
            ->value('avg_hours');

        $avgShipLabel = $avgShipTime
            ? round($avgShipTime).' jam'
            : '-';

        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();

        $score = $this->calculateScore($fulfillmentRate, $cancellationRate, $avgProcessTime, $pendingOrders);

        return [
            Stat::make('Skor Performa', $score.'/100')
                ->description('Berdasarkan fulfill, cancel rate, & waktu proses')
                ->descriptionIcon('heroicon-o-star')
                ->color($score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger')),

            Stat::make('Fulfillment Rate', $fulfillmentRate.'%')
                ->description("{$completedOrders30d}/{$totalOrders30d} pesanan selesai (30H)")
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($fulfillmentRate >= 90 ? 'success' : ($fulfillmentRate >= 70 ? 'warning' : 'danger')),

            Stat::make('Cancel Rate', $cancellationRate.'%')
                ->description("{$cancelledOrders30d} pesanan dibatalkan (30H)")
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($cancellationRate <= 5 ? 'success' : ($cancellationRate <= 15 ? 'warning' : 'danger')),

            Stat::make('Waktu Proses', $avgProcessLabel)
                ->description('Rata-rata created → shipped')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Waktu Kirim', $avgShipLabel)
                ->description('Rata-rata shipped → delivered')
                ->descriptionIcon('heroicon-o-truck')
                ->color('info'),

            Stat::make('Menunggu Diproses', (string) $pendingOrders)
                ->description('Pesanan pending')
                ->descriptionIcon('heroicon-o-hourglass')
                ->color($pendingOrders > 10 ? 'danger' : 'warning'),

            Stat::make('Sedang Diproses', (string) $processingOrders)
                ->description('Pesanan sedang disiapkan')
                ->descriptionIcon('heroicon-o-cog-6-tooth')
                ->color('info'),
        ];
    }

    private function calculateScore(float $fulfillment, float $cancellation, ?float $avgProcessHours, int $pending): int
    {
        $score = 0;

        $score += min(40, $fulfillment * 0.4);
        $score += max(0, 30 - ($cancellation * 2));
        if ($avgProcessHours !== null) {
            $score += max(0, 30 - ($avgProcessHours * 0.5));
        } else {
            $score += 15;
        }

        if ($pending > 20) {
            $score -= 10;
        } elseif ($pending > 10) {
            $score -= 5;
        }

        return (int) max(0, min(100, round($score)));
    }
}
