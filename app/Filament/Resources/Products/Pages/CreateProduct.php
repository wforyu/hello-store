<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $category = isset($data['category_id']) ? Category::find($data['category_id']) : null;

        if (blank($data['sku'] ?? null)) {
            $data['sku'] = Product::generateSku($category?->name);
        }

        foreach ($data['variants'] ?? [] as $i => $variant) {
            if (blank($variant['sku'] ?? null)) {
                $data['variants'][$i]['sku'] = Product::generateSku($variant['name'] ?? null);
            }
        }

        return $data;
    }
}
