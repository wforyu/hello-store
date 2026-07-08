<?php

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        if (isset($data['items'])) {
            $data['total_amount'] = collect($data['items'])->sum('subtotal');
        }

        return $data;
    }
}
