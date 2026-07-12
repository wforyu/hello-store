<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->helperText('Nama lengkap pengguna (customer/kasir/admin).'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->helperText('Email untuk login. Pastikan unik.'),
                DateTimePicker::make('email_verified_at')
                    ->label('Email Terverifikasi Pada')
                    ->helperText('Kosongkan jika email belum diverifikasi. Isi tanggal untuk tandai sudah verifikasi.'),
                Select::make('segment')
                    ->label('Segmen')
                    ->helperText('Bronze: 1x poin, Silver: 1.2x, Gold: 1.5x, Platinum: 2x')
                    ->options([
                        'bronze' => 'Bronze',
                        'silver' => 'Silver',
                        'gold' => 'Gold',
                        'platinum' => 'Platinum',
                    ])
                    ->default('bronze')
                    ->required(),
                Select::make('role')
                    ->label('Role')
                    ->options([
                        'customer' => 'Customer',
                        'cashier' => 'Kasir',
                        'admin' => 'Admin',
                    ])
                    ->default('customer')
                    ->required()
                    ->helperText('customer = akses toko saja | kasir = akses POS saja | admin = akses panel penuh.'),
                TextInput::make('password')
                    ->label('Kata Sandi')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state))
                    ->helperText('Minimal 8 karakter. Kosongkan jika tidak ingin mengubah password.'),
            ]);
    }
}
