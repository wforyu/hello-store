<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
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

        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->get();

        $this->salesData = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total'),
            'total_products' => OrderItem::whereHas('order', fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))->sum('quantity'),
            'average_order' => $orders->count() > 0 ? round($orders->sum('total') / $orders->count()) : 0,
        ];

        $totalExpense = Expense::whereBetween('expense_date', [$start, $end])->sum('amount');

        $this->profitData = [
            'revenue' => $this->salesData['total_revenue'],
            'expense' => $totalExpense,
            'profit' => $this->salesData['total_revenue'] - $totalExpense,
        ];

        $this->topProducts = OrderItem::selectRaw('product_id, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(20)
            ->get()
            ->map(function ($item) {
                $product = Product::find($item->product_id);

                return [
                    'name' => $product?->name ?? 'Produk #'.$item->product_id,
                    'qty' => $item->total_qty,
                    'revenue' => $item->total_revenue,
                ];
            })
            ->toArray();

        $this->topCategories = OrderItem::selectRaw('products.category_id, SUM(order_items.quantity) as total_qty')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))
            ->groupBy('products.category_id')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get()
            ->map(function ($item) {
                $category = Category::find($item->category_id);

                return [
                    'name' => $category?->name ?? 'Kategori #'.$item->category_id,
                    'qty' => $item->total_qty,
                ];
            })
            ->toArray();
    }
}
