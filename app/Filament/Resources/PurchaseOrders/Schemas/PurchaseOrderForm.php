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
                            ->required()
                            ->helperText('Pilih supplier yang akan memesan barang'),
                        TextInput::make('order_number')
                            ->label('No. PO')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Nomor unik purchase order, contoh: PO-20260718'),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'ordered' => 'Dipesan',
                                'partial' => 'Sebagian Diterima',
                                'received' => 'Diterima Lengkap',
                                'cancelled' => 'Dibatalkan',
                            ])
                            ->default('draft')
                            ->helperText('Draft → Dipesan → Diterima. Stok otomatis bertambah saat "Diterima"'),
                        DateTimePicker::make('ordered_at')
                            ->label('Tanggal Pesan')
                            ->helperText('Kapan PO dikirim ke supplier'),
                        DateTimePicker::make('received_at')
                            ->label('Tanggal Diterima')
                            ->helperText('Kapan barang diterima di gudang'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000)
                            ->helperText('Catatan untuk supplier (spesifikasi barang, kemasan, dll)'),
                    ]),
                Section::make('Item Pesanan')
                    ->description('Pilih produk dan isi jumlah yang dipesan. Harga beli bisa berbeda dari harga jual.')
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
                                    })
                                    ->helperText('Pilih produk dari daftar'),
                                TextInput::make('product_name')
                                    ->label('Nama Produk')
                                    ->hidden(),
                                TextInput::make('product_sku')
                                    ->label('SKU')
                                    ->disabled()
                                    ->helperText('Auto-fill dari produk'),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('unit_cost') ?? 0)))
                                    ->helperText('Jumlah unit yang dipesan'),
                                TextInput::make('unit_cost')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('quantity') ?? 0)))
                                    ->helperText('Harga beli per unit dari supplier'),
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
                            ->dehydrated()
                            ->helperText('Total sebelum pajak'),
                        TextInput::make('tax')
                            ->label('Pajak')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->helperText('Pajak pembelian (PPN masukan)'),
                        TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Subtotal + Pajak'),
                    ]),
            ]);
    }
}
