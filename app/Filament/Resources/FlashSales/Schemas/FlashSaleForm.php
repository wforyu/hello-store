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
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', str($state)->slug()))
                            ->helperText('Nama promo yang tampil di halaman depan'),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL slug, auto-generated dari nama'),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull()
                            ->maxLength(500)
                            ->helperText('Deskripsi singkat flash sale (muncul di banner)'),
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->required()
                            ->seconds(false)
                            ->helperText('Waktu flash sale dimulai'),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->required()
                            ->seconds(false)
                            ->after('start_time')
                            ->helperText('Waktu flash sale berakhir'),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'scheduled' => 'Terjadwal',
                                'active' => 'Aktif',
                                'ended' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('scheduled')
                            ->helperText('Terjadwal = belum mulai, Aktif = sedang berjalan'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan untuk menyembunyikan flash sale'),
                        FileUpload::make('banner_image')
                            ->label('Banner')
                            ->image()
                            ->maxSize(1024)
                            ->directory('flash-sales')
                            ->columnSpanFull()
                            ->helperText('Banner promosi untuk flash sale (maks 1MB)'),
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
                                    ->required()
                                    ->helperText('Produk yang akan didiskon'),
                                Select::make('discount_type')
                                    ->label('Jenis Diskon')
                                    ->options([
                                        'percentage' => 'Persen (%)',
                                        'nominal' => 'Nominal (Rp)',
                                    ])
                                    ->default('percentage')
                                    ->required()
                                    ->helperText('Persen: potongan %, Nominal: potongan Rp tetap'),
                                TextInput::make('discount_value')
                                    ->label('Nilai Diskon')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->helperText('Persen (max 100) atau nominal (max harga normal)'),
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
