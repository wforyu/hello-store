<?php

namespace App\Filament\Resources\FlashSales\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FlashSaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Flash Sale')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Flash Sale')
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
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->required()
                            ->seconds(false)
                            ->after('start_time'),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'scheduled' => 'Terjadwal',
                                'active' => 'Aktif',
                                'ended' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('scheduled'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                        FileUpload::make('banner_image')
                            ->label('Banner')
                            ->image()
                            ->maxSize(1024)
                            ->directory('flash-sales')
                            ->columnSpanFull(),
                    ]),
                Section::make('Produk Flash Sale')
                    ->schema([
                        Repeater::make('products')
                            ->columns(3)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('discount_type')
                                    ->label('Jenis Diskon')
                                    ->options([
                                        'percentage' => 'Persen (%)',
                                        'nominal' => 'Nominal (Rp)',
                                    ])
                                    ->default('percentage')
                                    ->required(),
                                TextInput::make('discount_value')
                                    ->label('Nilai Diskon')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0),
                                TextInput::make('max_qty')
                                    ->label('Maks. Terjual')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('0 = tidak terbatas'),
                            ])
                            ->addActionLabel('Tambah Produk')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
