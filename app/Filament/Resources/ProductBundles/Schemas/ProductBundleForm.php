<?php

namespace App\Filament\Resources\ProductBundles\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Bundle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Bundle')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', str($state)->slug())),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull()
                            ->maxLength(500),
                        TextInput::make('bundle_price')
                            ->label('Harga Bundle')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->seconds(false),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->seconds(false)
                            ->after('start_time'),
                        FileUpload::make('image')
                            ->label('Gambar Bundle')
                            ->image()
                            ->maxSize(1024)
                            ->directory('bundles'),
                    ]),
                Section::make('Produk dalam Bundle')
                    ->schema([
                        Repeater::make('products')
                            ->relationship()
                            ->columns(3)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),
                            ])
                            ->addActionLabel('Tambah Produk')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
            ]);
    }
}
