<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->helperText('Nama kategori pengeluaran (contoh: Listrik, Sewa, Gaji Karyawan).')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Otomatis terisi. Gunakan huruf kecil dan tanda strip.'),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->helperText('Penjelasan kategori (opsional).'),
            ]);
    }
}
