<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
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
                TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

                    BulkAction::make('bulkActivate')
                        ->label('Aktifkan')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aktifkan Produk')
                        ->modalDescription('Tandai produk yang dipilih sebagai aktif dan tampil di toko.')
                        ->modalSubmitActionLabel('Ya, Aktifkan')
                        ->action(fn ($records) => static::bulkSetBoolean($records, 'is_active', true, 'diaktifkan')),

                    BulkAction::make('bulkDeactivate')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Nonaktifkan Produk')
                        ->modalDescription('Sembunyikan produk yang dipilih dari toko.')
                        ->modalSubmitActionLabel('Ya, Nonaktifkan')
                        ->action(fn ($records) => static::bulkSetBoolean($records, 'is_active', false, 'dinonaktifkan')),

                    BulkAction::make('bulkSetFeatured')
                        ->label('Jadikan Unggulan')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Jadikan Produk Unggulan')
                        ->modalDescription('Tampilkan produk yang dipilih di bagian unggulan toko.')
                        ->modalSubmitActionLabel('Ya, Set Unggulan')
                        ->action(fn ($records) => static::bulkSetBoolean($records, 'featured', true, 'dijadikan unggulan')),

                    BulkAction::make('bulkUnsetFeatured')
                        ->label('Hapus Unggulan')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Hapus Status Unggulan')
                        ->modalDescription('Hapus status unggulan dari produk yang dipilih.')
                        ->modalSubmitActionLabel('Ya, Hapus Unggulan')
                        ->action(fn ($records) => static::bulkSetBoolean($records, 'featured', false, 'status unggulan dihapus')),

                    BulkAction::make('bulkUpdateStock')
                        ->label('Update Stok')
                        ->icon('heroicon-o-archive-box')
                        ->color('info')
                        ->form([
                            TextInput::make('stock')
                                ->label('Stok Baru')
                                ->required()
                                ->integer()
                                ->minValue(0),
                            TextInput::make('adjustment')
                                ->label('Penyesuaian (+/-)')
                                ->integer()
                                ->helperText('Isi untuk menambah/mengurangi stok. Kosongkan untuk set langsung.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Update Stok Massal')
                        ->modalDescription('Ubah stok untuk semua produk yang dipilih.')
                        ->modalSubmitActionLabel('Ya, Update Stok')
                        ->action(function ($records, array $data) {
                            foreach ($records as $product) {
                                $oldStock = $product->stock;

                                if (isset($data['adjustment']) && $data['adjustment'] !== null && $data['adjustment'] !== '') {
                                    $product->stock = max(0, $product->stock + (int) $data['adjustment']);
                                } else {
                                    $product->stock = (int) $data['stock'];
                                }

                                $diff = $product->stock - $oldStock;
                                $product->saveQuietly();

                                if ($diff !== 0) {
                                    $product->recordStockHistory($diff, 'manual', 'Bulk update stok dari admin');
                                }
                            }

                            FilamentNotification::make()
                                ->title('Stok Diperbarui')
                                ->body($records->count().' produk berhasil diupdate stoknya.')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function bulkSetBoolean($records, string $field, bool $value, string $actionLabel): void
    {
        $records->each->update([$field => $value]);

        FilamentNotification::make()
            ->title('Produk Diperbarui')
            ->body($records->count().' produk berhasil '.$actionLabel.'.')
            ->success()
            ->send();
    }
}
