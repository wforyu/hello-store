<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['items'])) {
            $data['subtotal'] = collect($data['items'])->sum('subtotal');
            $data['total'] = $data['subtotal'] + ($data['tax'] ?? 0);
        }

        return $data;
    }
}
