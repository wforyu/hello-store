<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (isset($data['items'])) {
            $data['subtotal'] = collect($data['items'])->sum('subtotal');
            $data['total'] = $data['subtotal'] + ($data['tax'] ?? 0);
        }

        return $data;
    }
}
