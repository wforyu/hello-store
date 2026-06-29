<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('rating')
                    ->label('Rating')
                    ->options([1 => '⭐', 2 => '⭐⭐', 3 => '⭐⭐⭐', 4 => '⭐⭐⭐⭐', 5 => '⭐⭐⭐⭐⭐'])
                    ->required()
                    ->helperText('Rating yang diberikan pelanggan dari 1-5 bintang.'),
                Textarea::make('comment')
                    ->label('Komentar')
                    ->columnSpanFull()
                    ->helperText('Isi ulasan dari pelanggan.'),
                Toggle::make('is_approved')
                    ->label('Disetujui')
                    ->helperText('Hanya ulasan yang disetujui yang tampil di halaman produk.'),
                TextInput::make('product_id')
                    ->label('ID Produk')
                    ->numeric()
                    ->disabled(),
                TextInput::make('user_id')
                    ->label('ID Pengguna')
                    ->numeric()
                    ->disabled(),
            ]);
    }
}
