<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopCategoriesTableWidget extends BaseTableWidget
{
    protected static ?string $heading = 'Kategori Paling Laku';

    protected int|string|array $columnSpan = 4;

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::select([
                    DB::raw('MAX(order_items.id) as id'),
                    'categories.id as category_id',
                    'categories.name as category_name',
                    DB::raw('COUNT(order_items.id) as total_qty'),
                ])
                    ->join('products', 'order_items.product_id', '=', 'products.id')
                    ->join('categories', 'products.category_id', '=', 'categories.id')
                    ->whereHas('order', fn (Builder $q) => $q->where('payment_status', 'paid'))
                    ->groupBy('categories.id', 'categories.name')
                    ->orderByDesc('total_qty')
                    ->take(10)
            )
            ->columns([
                TextColumn::make('category_name')
                    ->label('Kategori'),
                TextColumn::make('total_qty')
                    ->label('Terjual')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
