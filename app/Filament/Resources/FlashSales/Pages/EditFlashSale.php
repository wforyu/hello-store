<?php

namespace App\Filament\Resources\FlashSales\Pages;

use App\Filament\Resources\FlashSales\FlashSaleResource;
use Filament\Resources\Pages\EditRecord;

class EditFlashSale extends EditRecord
{
    protected static string $resource = FlashSaleResource::class;

    protected array $productsData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['products'] = $this->record->products()->get()->map(fn ($product) => [
            'product_id' => $product->id,
            'discount_type' => $product->pivot->discount_type,
            'discount_value' => $product->pivot->discount_value,
            'max_qty' => $product->pivot->max_qty,
        ])->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->productsData = $data['products'] ?? [];
        unset($data['products']);

        return $data;
    }

    protected function afterSave(): void
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
        } else {
            $this->record->products()->sync([]);
        }
    }
}
