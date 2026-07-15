<?php

namespace App\Filament\Resources\SocialFollowClaims\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SocialFollowClaimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platform')
                    ->label('Platform')
                    ->options([
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok',
                    ])
                    ->required()
                    ->disabled(),
                TextInput::make('user.name')
                    ->label('Customer')
                    ->disabled()
                    ->helperText('Nama customer yang mengajukan claim'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ])
                    ->required()
                    ->helperText('Ubah status claim ini'),
                TextInput::make('reward_tier')
                    ->label('Reward Tier')
                    ->disabled()
                    ->helperText('Tier yang akan didapat customer jika disetujui'),
                TextInput::make('reward_points')
                    ->label('Poin Bonus')
                    ->numeric()
                    ->disabled()
                    ->helperText('Poin tambahan yang didapat customer jika disetujui'),
                Textarea::make('admin_notes')
                    ->label('Catatan Admin')
                    ->rows(3)
                    ->placeholder('Alasan approve/reject...')
                    ->helperText('Catatan untuk customer (opsional)'),
            ]);
    }
}
