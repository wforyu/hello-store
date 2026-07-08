<?php

namespace App\Filament\Resources\ProductBundles;

use App\Filament\Resources\ProductBundles\Pages\CreateProductBundle;
use App\Filament\Resources\ProductBundles\Pages\EditProductBundle;
use App\Filament\Resources\ProductBundles\Pages\ListProductBundles;
use App\Filament\Resources\ProductBundles\Schemas\ProductBundleForm;
use App\Filament\Resources\ProductBundles\Tables\ProductBundlesTable;
use App\Models\ProductBundle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductBundleResource extends Resource
{
    protected static ?string $model = ProductBundle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pemasaran';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductBundleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductBundlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductBundles::route('/'),
            'create' => CreateProductBundle::route('/create'),
            'edit' => EditProductBundle::route('/{record}/edit'),
        ];
    }
}
