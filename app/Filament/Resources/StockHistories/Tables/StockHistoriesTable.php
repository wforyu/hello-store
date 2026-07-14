<?php

namespace App\Filament\Resources\StockHistories\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'gray',
                        'order' => 'info',
                        'pos' => 'warning',
                        'adjustment' => 'danger',
                        'opname' => 'success',
                        'return' => 'warning',
                        'refund' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'order' => 'Pesanan Online',
                        'pos' => 'POS',
                        'adjustment' => 'Penyesuaian',
                        'opname' => 'Stok Opname',
                        'return' => 'Retur ke Supplier',
                        'refund' => 'Refund',
                        default => $state,
                    }),
                TextColumn::make('quantity_change')
                    ->label('Perubahan')
                    ->color(fn (int $state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state),
                TextColumn::make('stock_before')
                    ->label('Stok Sebelum')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stock_after')
                    ->label('Stok Sesudah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable(),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
