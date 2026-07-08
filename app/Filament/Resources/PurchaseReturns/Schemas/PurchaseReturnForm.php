<?php

namespace App\Filament\Resources\PurchaseReturns\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Retur')
                    ->columns(2)
                    ->schema([
                        TextInput::make('return_number')
                            ->label('No. Retur')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('purchase_order_id')
                            ->label('Dari PO')
                            ->relationship('purchaseOrder', 'order_number')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Dikirim',
                                'received' => 'Diterima Supplier',
                                'completed' => 'Selesai',
                                'rejected' => 'Ditolak',
                            ])
                            ->default('draft'),
                        Select::make('reason')
                            ->label('Alasan Retur')
                            ->required()
                            ->options([
                                'defective' => 'Cacat / Rusak',
                                'wrong_item' => 'Barang Salah',
                                'expired' => 'Kedaluwarsa',
                                'quality' => 'Kualitas Tidak Sesuai',
                                'other' => 'Lainnya',
                            ])
                            ->default('defective'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000),
                    ]),
                Section::make('Item Retur')
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
                                Textarea::make('reason')
                                    ->label('Alasan Detail')
                                    ->columnSpanFull(),
                            ])
                            ->addActionLabel('Tambah Item')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
                Section::make('Ringkasan')
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
