<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->label('Induk Kategori')
                    ->helperText('Pilih kategori utama jika ini adalah sub-kategori. Kosongkan jika ini kategori utama (induk).'),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->helperText('Nama kategori yang akan tampil di toko (contoh: Elektronik, Fashion Pria).'),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('Otomatis terisi dari nama. Gunakan huruf kecil dan tanda strip (contoh: fashion-pria).'),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->helperText('Penjelasan singkat tentang kategori ini (opsional).'),
                FileUpload::make('image')
                    ->label('Gambar')
                    ->image()
                    ->helperText('Gambar kategori (akan tampil di halaman utama toko).'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->helperText('Nonaktifkan untuk menyembunyikan kategori dari toko.'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Semakin kecil angka, semakin atas posisinya di tampilan toko.'),
            ]);
    }
}
