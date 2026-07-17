<?php

namespace App\Filament\Widgets;

use App\Models\SalesTarget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesTargetWidget extends BaseWidget
{
    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Target Penjualan';

    protected function getStats(): array
    {
        SalesTarget::syncCurrentData();

        $targets = SalesTarget::active()->get();

        if ($targets->isEmpty()) {
            return [
                Stat::make('Belum Ada Target', '-')
                    ->description('Buat target penjualan di menu Keuangan → Sales Targets')
                    ->descriptionIcon('heroicon-o-flag')
                    ->color('gray'),
            ];
        }

        $stats = [];

        foreach ($targets as $target) {
            $progress = $target->revenue_progress;
            $daysLeft = $target->days_remaining;
            $totalDays = $target->start_date->diffInDays($target->end_date) + 1;
            $daysPassed = max(1, $totalDays - $daysLeft);
            $expectedProgress = round(($daysPassed / max(1, $totalDays)) * 100, 1);
            $ahead = $progress >= $expectedProgress;

            $color = $progress >= 100 ? 'success' : ($ahead ? 'info' : 'danger');

            $desc = 'Rp '.number_format($target->current_amount, 0, ',', '.').
                ' / Rp '.number_format($target->target_amount, 0, ',', '.').
                ' ('.$daysLeft.' hari lagi)';

            $stats[] = Stat::make($target->name, $progress.'%')
                ->description($desc)
                ->descriptionIcon($ahead ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down')
                ->color($color);
        }

        return $stats;
    }
}
