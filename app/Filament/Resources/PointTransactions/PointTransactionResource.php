<?php

namespace App\Filament\Resources\PointTransactions;

use App\Filament\Resources\PointTransactions\Pages\ListPointTransactions;
use App\Models\PointTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class PointTransactionResource extends Resource
{
    protected static ?string $model = PointTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengguna';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPointTransactions::route('/'),
        ];
    }
}
