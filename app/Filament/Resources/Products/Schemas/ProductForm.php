<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->helperText('Pilih kategori yang sesuai. Produk akan muncul di halaman kategori tersebut.'),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->helperText('Nama produk yang akan dilihat pelanggan (contoh: Kemeja Flanel Pria).'),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->helperText('Otomatis terisi. Gunakan huruf kecil dan tanda strip.'),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->helperText('Penjelasan detail produk. Akan tampil di halaman detail produk.'),
                TextInput::make('price')
                    ->label('Harga')
                    ->required()
                    ->prefix('Rp')
                    ->helperText('Harga jual produk. Titik (.) otomatis muncul.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                TextInput::make('compare_price')
                    ->label('Harga Sebelumnya')
                    ->prefix('Rp')
                    ->helperText('Harga asli sebelum diskon. Jika diisi, akan tampil coretan harga + badge diskon di toko.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                TextInput::make('stock')
                    ->label('Stok')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Jumlah barang tersedia. Stok akan berkurang otomatis saat ada pembelian.'),
                TextInput::make('sku')
                    ->label('SKU')
                    ->helperText('Kode unik produk untuk identifikasi internal (opsional).'),
                TextInput::make('weight')
                    ->label('Berat')
                    ->numeric()
                    ->suffix('kg')
                    ->helperText('Digunakan untuk kalkulasi ongkos kirim.'),
                FileUpload::make('images')
                    ->label('Gambar')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->visibility('public')
                    ->reorderable()
                    ->columnSpanFull()
                    ->helperText('Upload 1 atau lebih gambar produk. Urutkan dengan drag. Gambar pertama akan jadi thumbnail.'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktifkan untuk menyembunyikan produk dari toko.'),
                Toggle::make('featured')
                    ->label('Unggulan')
                    ->helperText('Aktifkan untuk menampilkan produk di section "Produk Unggulan" halaman utama.'),
                TextInput::make('meta_title')
                    ->label('Meta Title')
                    ->maxLength(70)
                    ->helperText('Judul untuk SEO (max 70 karakter). Kosongkan = pakai nama produk.'),
                TextInput::make('meta_description')
                    ->label('Meta Description')
                    ->maxLength(160)
                    ->columnSpanFull()
                    ->helperText('Deskripsi untuk SEO (max 160 karakter). Muncul di hasil pencarian Google.'),
            ]);
    }
}
