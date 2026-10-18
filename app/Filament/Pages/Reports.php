<?php

namespace App\Filament\Pages;

use App\Services\SalesReportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 5;

    protected ?string $heading = 'Laporan';

    protected string $view = 'filament.pages.reports';

    public ?array $data = [];

    public array $salesData = [];

    public array $profitData = [];

    public array $topProducts = [];

    public array $topCategories = [];

    /**
     * Detail pesanan pada periode terpilih, dipangkas jadi array biasa supaya
     * ringan diserialisasi Livewire (Collection besar bikin payload membengkak).
     */
    public array $orderRows = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'month',
            'startDate' => now()->startOfMonth()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
        ]);

        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Periode')
                    ->options([
                        'today' => 'Hari Ini',
                        'week' => 'Minggu Ini',
                        'month' => 'Bulan Ini',
                        'year' => 'Tahun Ini',
                        'custom' => 'Kustom',
                    ])
                    ->live()
                    ->afterStateUpdated(fn () => $this->filterByPeriod()),
                DatePicker::make('startDate')
                    ->label('Tanggal Mulai'),
                DatePicker::make('endDate')
                    ->label('Tanggal Akhir'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function filterByPeriod(): void
    {
        $period = $this->data['period'] ?? 'month';

        switch ($period) {
            case 'today':
                $this->data['startDate'] = now()->format('Y-m-d');
                $this->data['endDate'] = now()->format('Y-m-d');
                break;
            case 'week':
                $this->data['startDate'] = now()->startOfWeek()->format('Y-m-d');
                $this->data['endDate'] = now()->format('Y-m-d');
                break;
            case 'month':
                $this->data['startDate'] = now()->startOfMonth()->format('Y-m-d');
                $this->data['endDate'] = now()->format('Y-m-d');
                break;
            case 'year':
                $this->data['startDate'] = now()->startOfYear()->format('Y-m-d');
                $this->data['endDate'] = now()->format('Y-m-d');
                break;
        }

        $this->loadData();
    }

    public function loadData(): void
    {
        $start = isset($this->data['startDate']) ? Carbon::parse($this->data['startDate'])->startOfDay() : now()->startOfMonth();
        $end = isset($this->data['endDate']) ? Carbon::parse($this->data['endDate'])->endOfDay() : now()->endOfDay();

        // Angka laporan dihitung di SalesReportService — sumber yang sama dipakai
        // export CSV, jadi isi layar dan isi file tidak mungkin melenceng.
        $report = app(SalesReportService::class)->build($start, $end);

        $this->salesData = $report['sales'];
        $this->profitData = $report['profit'];
        $this->topProducts = $report['topProducts'];
        $this->topCategories = $report['topCategories'];

        $this->orderRows = $report['orders']
            ->map(fn ($order) => [
                'number' => $order->order_number,
                'date' => $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-',
                'status' => $order->status,
                'total' => (float) $order->total,
            ])
            ->all();
    }
}
