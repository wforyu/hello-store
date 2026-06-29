<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expense_category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->label('Kategori')
                    ->helperText('Pilih kategori pengeluaran (contoh: Listrik, Sewa, Gaji).'),
                TextInput::make('amount')
                    ->label('Jumlah')
                    ->required()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->helperText('Nominal pengeluaran.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Keterangan detail pengeluaran (contoh: Bayar listrik bulan Juni).'),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Dicatat oleh')
                    ->helperText('Otomatis terisi. Ubah jika perlu.'),
                DateTimePicker::make('expense_date')
                    ->required()
                    ->default(now())
                    ->label('Tanggal')
                    ->helperText('Tanggal pengeluaran terjadi.'),
            ]);
    }
}
