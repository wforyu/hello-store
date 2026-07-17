<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopProductsTableWidget extends BaseTableWidget
{
    protected static ?string $heading = 'Produk Paling Laku';

    protected int|string|array $columnSpan = 4;

    protected static ?int $sort = 6;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::select('product_id', DB::raw('MAX(order_items.id) as id'), DB::raw('SUM(quantity) as total_qty'))
                    ->whereHas('order', fn (Builder $q) => $q->where('payment_status', 'paid'))
                    ->groupBy('product_id')
                    ->orderByDesc('total_qty')
                    ->take(10)
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable(),
                TextColumn::make('total_qty')
                    ->label('Terjual')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('warning'),
            ])
            ->paginated(false);
    }
}
