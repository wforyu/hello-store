<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi PO')
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('order_number')
                            ->label('No. PO')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'ordered' => 'Dipesan',
                                'partial' => 'Sebagian',
                                'received' => 'Diterima',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('draft'),
                        DateTimePicker::make('ordered_at')
                            ->label('Tanggal Pesan'),
                        DateTimePicker::make('received_at')
                            ->label('Tanggal Diterima'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000),
                    ]),
                Section::make('Item Pesanan')
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
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('product_name', $product->name);
                                            $set('product_sku', $product->sku);
                                        }
                                    }),
                                TextInput::make('product_name')
                                    ->label('Nama Produk')
                                    ->hidden(),
                                TextInput::make('product_sku')
                                    ->label('SKU')
                                    ->disabled(),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('unit_cost') ?? 0))),
                                TextInput::make('unit_cost')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('quantity') ?? 0))),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->addActionLabel('Tambah Item')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
                Section::make('Ringkasan')
                    ->columns(3)
                    ->schema([
                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('tax')
                            ->label('Pajak')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),
                        TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
