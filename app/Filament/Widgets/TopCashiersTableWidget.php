<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopCashiersTableWidget extends BaseTableWidget
{
    protected static ?string $heading = 'Kasir Terbaik';

    protected int|string|array $columnSpan = 4;

    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::select('user_id', DB::raw('MAX(orders.id) as id'), DB::raw('COUNT(*) as total_orders'), DB::raw('SUM(total) as total_revenue'))
                    ->where('payment_status', 'paid')
                    ->whereHas('user', fn (Builder $q) => $q->whereIn('role', ['admin', 'cashier']))
                    ->groupBy('user_id')
                    ->orderByDesc('total_orders')
                    ->take(10)
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('Kasir'),
                TextColumn::make('total_orders')
                    ->label('Order')
                    ->alignCenter()
                    ->weight('bold')
                    ->color('warning'),
                TextColumn::make('total_revenue')
                    ->label('Pendapatan')
                    ->alignEnd()
                    ->money('IDR'),
            ])
            ->paginated(false);
    }
}
