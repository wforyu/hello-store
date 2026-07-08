<?php

namespace App\Filament\Resources\FlashSales\Pages;

use App\Filament\Resources\FlashSales\FlashSaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFlashSale extends CreateRecord
{
    protected static string $resource = FlashSaleResource::class;

    protected array $productsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->productsData = $data['products'] ?? [];
        unset($data['products']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $products = collect($this->productsData);

        if ($products->isNotEmpty()) {
            $this->record->products()->sync(
                $products->mapWithKeys(fn ($item) => [
                    $item['product_id'] => [
                        'discount_type' => $item['discount_type'],
                        'discount_value' => $item['discount_value'],
                        'max_qty' => $item['max_qty'] ?? 0,
                    ],
                ])
            );
        }
    }
}
