<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('attributes')
                    ->label('Atribut')
                    ->formatStateUsing(fn ($record) => $record->attributes->groupBy('type')->map(fn ($items, $type) => ucfirst($type).': '.$items->pluck('value')->implode(', '))->implode(' | '))
                    ->wrap()
                    ->limit(50),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('cost_price')
                    ->label('Harga Modal')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('compare_price')
                    ->label('Harga Sebelumnya')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('weight')
                    ->label('Berat')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                IconColumn::make('featured')
                    ->label('Unggulan')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stock')
                    ->label('Stok')
                    ->options([
                        'low' => 'Stok Menipis (≤ 5)',
                        'out' => 'Habis (0)',
                    ])
                    ->query(function (Builder $query, $state) {
                        if ($state['value'] === 'low') {
                            $query->where('stock', '<=', 5)->where('stock', '>', 0);
                        }
                        if ($state['value'] === 'out') {
                            $query->where('stock', 0);
                        }
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('barcode')
                    ->label('Barcode')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn ($record) => route('barcode.product', $record))
                    ->openUrlInNewTab()
                    ->color('warning'),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
