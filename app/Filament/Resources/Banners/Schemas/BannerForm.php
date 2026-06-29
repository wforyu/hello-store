<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Banner')
                    ->required()
                    ->helperText('Nama internal untuk identifikasi banner (tidak tampil ke pelanggan).'),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'popup' => 'Pop-up',
                        'announcement' => 'Pengumuman',
                        'hero' => 'Hero',
                    ])
                    ->required()
                    ->default('popup')
                    ->helperText('Pop-up = modal saat buka toko | Pengumuman = bar info di atas navbar | Hero = belum terintegrasi.'),
                TextInput::make('title')
                    ->label('Judul')
                    ->helperText('Judul yang tampil di banner/pop-up.'),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->helperText('Teks deskripsi yang tampil di banner.'),
                FileUpload::make('image')
                    ->label('Gambar')
                    ->image()
                    ->columnSpanFull()
                    ->helperText('Gambar banner. Ukuran recommended: 1200×600px.'),
                TextInput::make('link')
                    ->label('Tautan')
                    ->helperText('Link tujuan saat banner diklik (contoh: /products atau URL lengkap).'),
                TextInput::make('link_label')
                    ->label('Label Tombol')
                    ->helperText('Teks tombol CTA (contoh: "Lihat Promo").'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->helperText('Nonaktifkan untuk menyembunyikan banner sementara tanpa hapus.'),
                DateTimePicker::make('start_at')
                    ->label('Mulai')
                    ->helperText('Banner otomatis aktif mulai tanggal ini. Kosongkan untuk aktif segera.'),
                DateTimePicker::make('end_at')
                    ->label('Selesai')
                    ->helperText('Banner otomatis nonaktif setelah tanggal ini. Kosongkan untuk tanpa batas.'),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->helperText('Urutan tampilan (angka terkecil tampil lebih dulu).'),
            ]);
    }
}
