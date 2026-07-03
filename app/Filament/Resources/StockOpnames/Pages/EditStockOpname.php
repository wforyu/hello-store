<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Resources\Pages\EditRecord;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;

        if ($record->status === 'completed' && $record->wasChanged('status')) {
            foreach ($record->items as $item) {
                if ($item->difference !== 0) {
                    $product = $item->product;
                    $product->stock += $item->difference;
                    $product->save();

                    $product->recordStockHistory(
                        $item->difference,
                        'opname',
                        'Stok opname: ' . ($item->notes ?? 'Penyesuaian stok'),
                        'StockOpname',
                        $record->id
                    );
                }
            }
        }
    }
}
