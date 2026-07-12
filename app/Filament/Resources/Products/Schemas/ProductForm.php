<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->label('Brand')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Pilih brand produk (opsional).'),
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
                TextInput::make('cost_price')
                    ->label('Harga Modal')
                    ->prefix('Rp')
                    ->helperText('Harga beli/pokok produk (untuk hitung laba). Titik (.) otomatis muncul.')
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
                Section::make('Produk Digital')
                    ->description('Untuk produk digital seperti ebook, software, license key')
                    ->schema([
                        Toggle::make('is_digital')
                            ->label('Produk Digital')
                            ->helperText('Aktifkan jika produk ini adalah file digital (PDF, ZIP, gambar)')
                            ->live()
                            ->columnSpanFull(),
                        FileUpload::make('digital_file')
                            ->label('File Digital')
                            ->acceptedFileTypes(['application/pdf', 'application/zip', 'application/x-rar-compressed', 'image/*'])
                            ->maxSize(102400)
                            ->directory('digital-products')
                            ->helperText('Upload file produk digital (maks 100MB)')
                            ->visible(fn ($get) => $get('is_digital'))
                            ->columnSpanFull(),
                        Textarea::make('license_key')
                            ->label('License Key')
                            ->helperText('Serial number atau license key (akan dikirim ke customer setelah pembelian)')
                            ->rows(2)
                            ->visible(fn ($get) => $get('is_digital'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Atribut Produk')
                    ->description('Warna, Ukuran, Bahan, dll.')
                    ->schema([
                        Repeater::make('attributes')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label('Tipe')
                                    ->options([
                                        'color' => 'Warna',
                                        'size' => 'Ukuran',
                                        'material' => 'Bahan',
                                    ])
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('value')
                                    ->label('Nilai')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(1),
                                TextInput::make('label')
                                    ->label('Label')
                                    ->maxLength(100)
                                    ->columnSpan(1),
                                TextInput::make('sort_order')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Atribut'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Varian Produk')
                    ->description('Kombinasi atribut dengan stok & harga sendiri (contoh: Merah - XL, Biru - M)')
                    ->schema([
                        Repeater::make('variants')
                            ->label('')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Varian')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Contoh: "Merah - XL", "Biru - M"')
                                    ->columnSpan(2),
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(100)
                                    ->columnSpan(1),
                                TextInput::make('price')
                                    ->label('Harga (opsional)')
                                    ->prefix('Rp')
                                    ->helperText('Kosongkan = pakai harga produk')
                                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null)
                                    ->columnSpan(1),
                                TextInput::make('stock')
                                    ->label('Stok')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('weight')
                                    ->label('Berat (kg)')
                                    ->numeric()
                                    ->helperText('Kosongkan = pakai berat produk')
                                    ->columnSpan(1),
                                FileUpload::make('image')
                                    ->label('Gambar Varian')
                                    ->image()
                                    ->disk('public')
                                    ->directory('variant-images')
                                    ->columnSpan(2)
                                    ->helperText('Upload gambar khusus untuk varian ini (opsional)'),
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->default(true)
                                    ->columnSpan(1),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Tambah Varian'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
