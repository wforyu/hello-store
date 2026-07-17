<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class ProductAnalyticsWidget extends BaseTableWidget
{
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Analitik Produk (30 Hari)';

    public function table(Table $table): Table
    {
        $thirtyDaysAgo = now()->subDays(30);

        return $table
            ->query(
                Product::query()
                    ->where('is_active', true)
                    ->select('products.id', 'products.name', 'products.views_count', 'products.stock')
                    ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                    ->leftJoin('orders', function ($join) use ($thirtyDaysAgo) {
                        $join->on('order_items.order_id', '=', 'orders.id')
                            ->where('orders.payment_status', 'paid')
                            ->where('orders.created_at', '>=', $thirtyDaysAgo);
                    })
                    ->groupBy('products.id', 'products.name', 'products.views_count', 'products.stock')
                    ->selectRaw('
                        COALESCE(SUM(order_items.quantity), 0) as sold_30d,
                        COALESCE(SUM(order_items.subtotal), 0) as revenue_30d
                    ')
                    ->orderByDesc('revenue_30d')
                    ->limit(15)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Produk')
                    ->searchable()
                    ->limit(30)
                    ->weight('bold'),
                TextColumn::make('views_count')
                    ->label('Total Views')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sold_30d')
                    ->label('Terjual (30H)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conversion_rate')
                    ->label('Konversi')
                    ->state(function ($record): string {
                        $views = $record->views_count ?? 0;
                        $sold = $record->sold_30d ?? 0;

                        return $views > 0
                            ? round(($sold / $views) * 100, 1).'%'
                            : '0%';
                    })
                    ->sortable(),
                TextColumn::make('revenue_30d')
                    ->label('Revenue (30H)')
                    ->money('IDR')
                    ->sortable(),
            ]);
    }
}
