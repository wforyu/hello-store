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
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', str($state)->slug())),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
                Section::make('Kontak')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_person')
                            ->label('Nama Kontak')
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('No. Telepon')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make('Alamat')
                    ->columns(2)
                    ->schema([
                        Textarea::make('address')
                            ->label('Alamat')
                            ->columnSpanFull()
                            ->maxLength(500),
                        TextInput::make('city')
                            ->label('Kota')
                            ->maxLength(100),
                        TextInput::make('province')
                            ->label('Provinsi')
                            ->maxLength(100),
                        TextInput::make('postal_code')
                            ->label('Kode Pos')
                            ->maxLength(10),
                    ]),
                Section::make('Catatan')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000),
                    ]),
            ]);
    }
}
