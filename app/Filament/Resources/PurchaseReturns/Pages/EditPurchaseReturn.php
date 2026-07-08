<?php

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['items'])) {
            $data['total_amount'] = collect($data['items'])->sum('subtotal');
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (in_array($record->status, ['completed', 'received', 'rejected']) && $record->wasChanged('status')) {
            if (in_array($record->status, ['completed', 'received'])) {
                DB::transaction(function () use ($record) {
                    foreach ($record->items as $item) {
                        $product = $item->product;

                        if (! $product) {
                            continue;
                        }

                        $product->stock -= $item->quantity;
                        $product->saveQuietly();

                        $product->recordStockHistory(
                            -$item->quantity,
                            'return',
                            'Retur ke supplier: '.($item->reason ?? $record->reason),
                            'PurchaseReturn',
                            $record->id
                        );
                    }
                });
            }
        }
    }
}
