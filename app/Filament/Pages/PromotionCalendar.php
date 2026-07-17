<?php

namespace App\Filament\Pages;

use App\Models\Coupon;
use App\Models\FlashSale;
use App\Models\ProductBundle;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use UnitEnum;

class PromotionCalendar extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Pemasaran';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Kalender Promosi';

    protected static ?string $slug = 'promotion-calendar';

    protected static ?string $navigationLabel = 'Kalender Promosi';

    protected string $view = 'filament.pages.promotion-calendar';

    public int $year;

    public int $month;

    public array $events = [];

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
        $this->loadEvents();
    }

    public function previousMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->loadEvents();
    }

    public function nextMonth(): void
    {
        $date = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
        $this->loadEvents();
    }

    public function loadEvents(): void
    {
        $start = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $this->events = [];

        $flashSales = FlashSale::where('start_time', '<=', $end)
            ->where('end_time', '>=', $start)
            ->get();

        foreach ($flashSales as $fs) {
            $fsStart = max($start->timestamp, $fs->start_time->timestamp);
            $fsEnd = min($end->timestamp, $fs->end_time->timestamp);
            $dStart = Carbon::createFromTimestamp($fsStart)->startOfDay();
            $dEnd = Carbon::createFromTimestamp($fsEnd)->endOfDay();

            for ($d = $dStart->copy(); $d->lte($dEnd); $d->addDay()) {
                $day = $d->day;
                $this->events[$day][] = [
                    'type' => 'flash_sale',
                    'label' => $fs->name,
                    'color' => '#f59e0b',
                ];
            }
        }

        $coupons = Coupon::where('is_active', true)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('starts_at', [$start, $end])
                    ->orWhereBetween('expires_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('starts_at', '<=', $start)
                            ->where('expires_at', '>=', $end);
                    });
            })
            ->get();

        foreach ($coupons as $coupon) {
            $cStart = $coupon->starts_at ? Carbon::parse($coupon->starts_at) : $start;
            $cEnd = $coupon->expires_at ? Carbon::parse($coupon->expires_at) : $end;
            $cStart = max($start->copy(), $cStart->startOfDay());
            $cEnd = min($end->copy(), $cEnd->endOfDay());

            for ($d = $cStart->copy(); $d->lte($cEnd); $d->addDay()) {
                $day = $d->day;
                $this->events[$day][] = [
                    'type' => 'coupon',
                    'label' => $coupon->code,
                    'color' => '#8b5cf6',
                ];
            }
        }

        $bundles = ProductBundle::where('is_active', true)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_time', [$start, $end])
                    ->orWhereBetween('end_time', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->where('start_time', '<=', $start)
                            ->where('end_time', '>=', $end);
                    });
            })
            ->get();

        foreach ($bundles as $bundle) {
            $bStart = $bundle->start_time ? Carbon::parse($bundle->start_time) : $start;
            $bEnd = $bundle->end_time ? Carbon::parse($bundle->end_time) : $end;
            $bStart = max($start->copy(), $bStart->startOfDay());
            $bEnd = min($end->copy(), $bEnd->endOfDay());

            for ($d = $bStart->copy(); $d->lte($bEnd); $d->addDay()) {
                $day = $d->day;
                $this->events[$day][] = [
                    'type' => 'bundle',
                    'label' => $bundle->name,
                    'color' => '#10b981',
                ];
            }
        }
    }

    public function getMonthName(): string
    {
        return Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    public function getCalendarDays(): array
    {
        $start = Carbon::create($this->year, $this->month, 1);
        $end = $start->copy()->endOfMonth();

        $daysInMonth = $end->day;
        $startDayOfWeek = $start->dayOfWeek;

        $days = [];

        for ($i = 0; $i < $startDayOfWeek; $i++) {
            $days[] = ['day' => null, 'events' => []];
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $isToday = $day === (int) now()->day
                && $this->month === (int) now()->month
                && $this->year === (int) now()->year;

            $days[] = [
                'day' => $day,
                'is_today' => $isToday,
                'events' => $this->events[$day] ?? [],
            ];
        }

        return $days;
    }
}
