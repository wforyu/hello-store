<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Opname')
                    ->columns(2)
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('No. Opname')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'in_progress' => 'Sedang Berjalan',
                                'completed' => 'Selesai',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('draft'),
                        DateTimePicker::make('completed_at')
                            ->label('Tanggal Selesai'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000),
                    ]),
                Section::make('Item Opname')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->columns(4)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(Product::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        $product = Product::with('stockHistories')->find($state);
                                        if ($product) {
                                            $set('product_name', $product->name);
                                            $set('product_sku', $product->sku);
                                            $set('system_stock', $product->stock);
                                        }
                                    }),
                                TextInput::make('product_name')
                                    ->label('Nama Produk')
                                    ->hidden(),
                                TextInput::make('product_sku')
                                    ->label('SKU')
                                    ->disabled(),
                                TextInput::make('system_stock')
                                    ->label('Stok Sistem')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('physical_stock')
                                    ->label('Stok Fisik')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $system = (int) ($get('system_stock') ?? 0);
                                        $physical = (int) ($state ?? 0);
                                        $set('difference', $physical - $system);
                                    }),
                                TextInput::make('difference')
                                    ->label('Selisih')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('notes')
                                    ->label('Catatan')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Tambah Item')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
            ]);
    }
}
