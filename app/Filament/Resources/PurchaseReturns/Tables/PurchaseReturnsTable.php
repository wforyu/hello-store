<?php

namespace App\Filament\Resources\PurchaseReturns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')
                    ->label('No. Retur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('purchase_order.order_number')
                    ->label('Dari PO')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'defective' => 'Cacat',
                        'wrong_item' => 'Salah Barang',
                        'expired' => 'Kedaluwarsa',
                        'quality' => 'Kualitas',
                        'other' => 'Lainnya',
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'info',
                        'received' => 'warning',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Draft',
                        'submitted' => 'Dikirim',
                        'received' => 'Diterima Supplier',
                        'completed' => 'Selesai',
                        'rejected' => 'Ditolak',
                        default => $state,
                    }),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'submitted' => 'Dikirim',
                        'received' => 'Diterima Supplier',
                        'completed' => 'Selesai',
                        'rejected' => 'Ditolak',
                    ]),
                SelectFilter::make('reason')
                    ->label('Alasan')
                    ->options([
                        'defective' => 'Cacat',
                        'wrong_item' => 'Salah Barang',
                        'expired' => 'Kedaluwarsa',
                        'quality' => 'Kualitas',
                        'other' => 'Lainnya',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
