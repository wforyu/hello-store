<?php

namespace App\Filament\Resources\ProductBundles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductBundlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Bundle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Produk')
                    ->counts('products'),
                TextColumn::make('bundle_price')
                    ->label('Harga Bundle')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total_original_price')
                    ->label('Harga Normal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('Selesai')
                    ->dateTime('d M Y')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
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
