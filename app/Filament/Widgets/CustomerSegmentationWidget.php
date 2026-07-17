<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Support\Facades\DB;

class CustomerSegmentationWidget extends BaseTableWidget
{
    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Segmentasi Pelanggan (RFM)';

    public function table(Table $table): Table
    {
        $now = now();

        return $table
            ->query(
                User::where('role', 'customer')
                    ->leftJoin('orders', function ($join) {
                        $join->on('users.id', '=', 'orders.user_id')
                            ->where('orders.payment_status', 'paid');
                    })
                    ->groupBy('users.id', 'users.name', 'users.email', 'users.segment', 'users.total_spent')
                    ->select([
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.segment',
                        'users.total_spent',
                        DB::raw('MAX(orders.created_at) as last_order_at'),
                        DB::raw('COUNT(DISTINCT orders.id) as order_count'),
                        DB::raw('COALESCE(SUM(orders.total), 0) as total_revenue'),
                    ])
                    ->selectRaw('
                        CASE
                            WHEN MAX(orders.created_at) >= ? THEN 3
                            WHEN MAX(orders.created_at) >= ? THEN 2
                            WHEN MAX(orders.created_at) IS NOT NULL THEN 1
                            ELSE 0
                        END as recency_score
                    ', [$now->copy()->subDays(30)->toDateTimeString(), $now->copy()->subDays(90)->toDateTimeString()])
                    ->selectRaw('
                        CASE
                            WHEN COUNT(DISTINCT orders.id) >= 10 THEN 3
                            WHEN COUNT(DISTINCT orders.id) >= 3 THEN 2
                            WHEN COUNT(DISTINCT orders.id) >= 1 THEN 1
                            ELSE 0
                        END as frequency_score
                    ')
                    ->selectRaw('
                        CASE
                            WHEN COALESCE(SUM(orders.total), 0) >= 5000000 THEN 3
                            WHEN COALESCE(SUM(orders.total), 0) >= 1000000 THEN 2
                            WHEN COALESCE(SUM(orders.total), 0) > 0 THEN 1
                            ELSE 0
                        END as monetary_score
                    ')
                    ->orderByDesc('total_revenue')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('segment')
                    ->label('Tier')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'platinum' => 'success',
                        'gold' => 'warning',
                        'silver' => 'info',
                        'diamond' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'bronze')),
                TextColumn::make('recency_score')
                    ->label('Recency')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        3 => 'success', 2 => 'warning', 1 => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        3 => '<30 hari', 2 => '30-90 hari', 1 => '>90 hari', default => 'Belum pernah',
                    })
                    ->html(),
                TextColumn::make('frequency_score')
                    ->label('Frequency')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        3 => 'success', 2 => 'warning', 1 => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        3 => '10+ order', 2 => '3-9 order', 1 => '1-2 order', default => '0',
                    }),
                TextColumn::make('monetary_score')
                    ->label('Monetary')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        3 => 'success', 2 => 'warning', 1 => 'danger', default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        3 => '>5jt', 2 => '1-5jt', 1 => '<1jt', default => '0',
                    }),
                TextColumn::make('order_count')
                    ->label('Orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('last_order_at')
                    ->label('Terakhir Order')
                    ->dateTime('d M Y')
                    ->sortable(),
            ]);
    }
}
