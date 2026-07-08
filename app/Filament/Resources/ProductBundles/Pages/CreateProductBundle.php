<?php

namespace App\Filament\Resources\ProductBundles\Pages;

use App\Filament\Resources\ProductBundles\ProductBundleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductBundle extends CreateRecord
{
    protected static string $resource = ProductBundleResource::class;

    protected array $productsData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->productsData = $data['products'] ?? [];
        unset($data['products']);

        $products = collect($this->productsData);
        $data['total_original_price'] = $products->sum(fn ($p) => ($p['quantity'] ?? 1) * ($p['unit_price'] ?? 0));

        return $data;
    }

    protected function afterCreate(): void
    {
        $products = collect($this->productsData);

        if ($products->isNotEmpty()) {
            $this->record->products()->sync(
                $products->mapWithKeys(fn ($item) => [
                    $item['product_id'] => [
                        'quantity' => $item['quantity'] ?? 1,
                    ],
                ])
            );
        }
    }
}
