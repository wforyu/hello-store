<?php

namespace App\Filament\Resources\Sliders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->helperText('Judul slide yang tampil di hero slider.'),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->helperText('Teks deskripsi yang tampil di slide.'),
                FileUpload::make('image')
                    ->label('Gambar')
                    ->image()
                    ->columnSpanFull()
                    ->helperText('Gambar slider. Ukuran recommended: 1200×600px.'),
                TextInput::make('link')
                    ->label('Tautan')
                    ->helperText('Link tujuan saat slide diklik (contoh: /products atau URL lengkap).'),
                TextInput::make('link_label')
                    ->label('Label Tombol')
                    ->helperText('Teks tombol CTA (contoh: "Lihat Promo").'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Nonaktifkan untuk menyembunyikan slide sementara tanpa hapus.'),
                DateTimePicker::make('start_at')
                    ->label('Mulai')
                    ->helperText('Slide otomatis aktif mulai tanggal ini. Kosongkan untuk aktif segera.'),
                DateTimePicker::make('end_at')
                    ->label('Selesai')
                    ->helperText('Slide otomatis nonaktif setelah tanggal ini. Kosongkan untuk tanpa batas.'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->helperText('Urutan tampilan (angka terkecil tampil lebih dulu).'),
            ]);
    }
}
