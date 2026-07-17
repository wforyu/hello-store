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
                            ->unique(ignoreRecord: true)
                            ->helperText('Nomor unik retur, contoh: RET-20260718'),
                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Supplier tujuan retur barang'),
                        Select::make('purchase_order_id')
                            ->label('Dari PO')
                            ->relationship('purchaseOrder', 'order_number')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Hubungkan ke PO asal (opsional)'),
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'submitted' => 'Dikirim ke Supplier',
                                'received' => 'Diterima Supplier',
                                'completed' => 'Selesai (Stok Balik)',
                                'rejected' => 'Ditolak Supplier',
                            ])
                            ->default('draft')
                            ->helperText('Stok otomatis bertambah saat status "Selesai"'),
                        Select::make('reason')
                            ->label('Alasan Retur')
                            ->required()
                            ->options([
                                'defective' => 'Cacat / Rusak',
                                'wrong_item' => 'Barang Salah Kirim',
                                'expired' => 'Kedaluwarsa',
                                'quality' => 'Kualitas Tidak Sesuai',
                                'other' => 'Lainnya',
                            ])
                            ->default('defective')
                            ->helperText('Alasan utama retur barang'),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->maxLength(1000)
                            ->helperText('Detail retur, bukti foto, dll'),
                    ]),
                Section::make('Item Retur')
                    ->description('Pilih produk yang akan diretur ke supplier. Harga beli dari produk akan digunakan untuk menghitung nilai retur.')
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
                                    ->helperText('Pilih produk yang diretur'),
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
                                    ->helperText('Jumlah unit yang diretur'),
                                TextInput::make('unit_cost')
                                    ->label('Harga Beli')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->required()
                                    ->default(0)
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('quantity') ?? 0)))
                                    ->helperText('Harga beli per unit (untuk klaim ke supplier)'),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                Textarea::make('reason')
                                    ->label('Alasan Detail')
                                    ->columnSpanFull()
                                    ->helperText('Jelaskan kondisi barang secara detail'),
                            ])
                            ->addActionLabel('Tambah Item')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
                Section::make('Ringkasan')
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Total Nilai Retur')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Total nilai retur = jumlah x harga beli'),
                    ]),
            ]);
    }
}
