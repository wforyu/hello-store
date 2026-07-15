<?php

namespace App\Filament\Resources\SocialFollowClaims;

use App\Filament\Resources\SocialFollowClaims\Pages\ListSocialFollowClaims;
use App\Models\SocialFollowClaim;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

class SocialFollowClaimResource extends Resource
{
    protected static ?string $model = SocialFollowClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 60;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Pengaturan';
    }

    public static function getNavigationLabel(): string
    {
        return 'Follow Claims';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Social Follow Claims';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialFollowClaims::route('/'),
        ];
    }
}
