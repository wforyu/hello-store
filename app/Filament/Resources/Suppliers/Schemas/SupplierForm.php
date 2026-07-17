<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Perusahaan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Supplier')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', str($state)->slug()))
                            ->helperText('Nama lengkap supplier / pemasok'),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated dari nama, bisa diubah manual'),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->helperText('URL website supplier (opsional)'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan untuk menyembunyikan supplier'),
                    ]),
                Section::make('Kontak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_person')
                            ->label('Nama Kontak')
                            ->maxLength(255)
                            ->helperText('Nama personel yang bisa dihubungi'),
                        TextInput::make('phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Format: 08xxx atau +62xxx'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Email untuk komunikasi order'),
                    ]),
                Section::make('Alamat')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->label('Alamat')
                            ->columnSpanFull()
                            ->maxLength(500)
                            ->helperText('Alamat lengkap gudang / kantor supplier'),
                        TextInput::make('city')
                            ->label('Kota')
                            ->maxLength(100)
                            ->helperText('Kota asal supplier'),
                        TextInput::make('province')
                            ->label('Provinsi')
                            ->maxLength(100)
                            ->helperText('Provinsi supplier'),
                        TextInput::make('postal_code')
                            ->label('Kode Pos')
                            ->maxLength(10)
                            ->helperText('Kode pos 5 digit'),
                    ]),
                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000)
                            ->helperText('Catatan internal tentang supplier (jam kerja, minimal order, dll)'),
                    ]),
            ]);
    }
}
