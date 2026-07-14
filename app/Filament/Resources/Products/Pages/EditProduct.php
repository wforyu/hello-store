<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjustStock')
                ->label('Sesuaikan Stok')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->form([
                    TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->required()
                        ->placeholder('+10 untuk tambah, -5 untuk kurangi')
                        ->helperText('Gunakan angka positif untuk menambah stok (restok/retur), negatif untuk mengurangi.'),
                    Select::make('reason')
                        ->label('Alasan')
                        ->options([
                            'restok' => 'Restok dari Gudang',
                            'retur' => 'Retur Pelanggan',
                            'adjustment' => 'Penyesuaian Stok',
                        ])
                        ->required(),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->placeholder('Misal: "Restok dari supplier PT ABC" atau "Retur order #ORD-xxx"'),
                ])
                ->action(function (array $data): void {
                    $qty = (int) $data['quantity'];
                    if ($qty === 0) {
                        return;
                    }

                    $product = $this->record;
                    $product->increment('stock', $qty);
                    $product->refresh();

                    $reasonLabel = match ($data['reason']) {
                        'restok' => 'Restok dari Gudang',
                        'retur' => 'Retur Pelanggan',
                        'adjustment' => 'Penyesuaian Stok',
                    };
                    $notes = $reasonLabel.($data['notes'] ? ' — '.$data['notes'] : '');

                    $product->recordStockHistory($qty, 'adjustment', $notes);
                }),
            DeleteAction::make(),
        ];
    }
}
