<?php

namespace App\Filament\Resources\ProductBundles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
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
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $total = $state > 0 ? $state : $record->products->sum(fn ($p) => $p->price * $p->pivot->quantity);

                        return 'Rp '.number_format($total, 0, ',', '.');
                    }),
                TextColumn::make('diskon')
                    ->label('Diskon')
                    ->getStateUsing(function ($record) {
                        $normal = $record->total_original_price > 0
                            ? $record->total_original_price
                            : $record->products->sum(fn ($p) => $p->price * $p->pivot->quantity);
                        $hemat = $normal - $record->bundle_price;

                        return $hemat;
                    })
                    ->formatStateUsing(function ($state) {
                        if ($state > 0) {
                            return "<span style='color:var(--success-600);font-weight:700;'>Rp ".number_format($state, 0, ',', '.').'</span>';
                        }
                        if ($state < 0) {
                            return "<span style='color:var(--danger-600);font-weight:700;'>-Rp ".number_format(abs($state), 0, ',', '.').'</span>';
                        }

                        return 'Rp 0';
                    })
                    ->html(),
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
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
