<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->label('Pesanan')
                    ->required()
                    ->helperText('Pilih pesanan yang terkait dengan pembayaran ini.'),
                Select::make('method')
                    ->label('Metode')
                    ->required()
                    ->options([
                        'manual_transfer' => 'Transfer Manual',
                        'cod' => 'COD (Bayar di Tempat)',
                        'cash' => 'Tunai (POS)',
                    ])
                    ->helperText('Pilih metode pembayaran yang digunakan.'),
                TextInput::make('amount')
                    ->label('Jumlah')
                    ->required()
                    ->prefix('Rp')
                    ->helperText('Jumlah uang yang dibayarkan.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ])
                    ->default('pending')
                    ->helperText('Ubah ke "Paid" jika pembayaran sudah dikonfirmasi.'),
                Placeholder::make('proof_image_preview')
                    ->label('Bukti Transfer')
                    ->content(fn ($record) => $record?->proof_image_url
                        ? new HtmlString('<a href="'.$record->proof_image_url.'" target="_blank"><img src="'.$record->proof_image_url.'" style="max-width:300px;border-radius:12px;border:1px solid #d1d5db;box-shadow:0 1px 3px rgba(0,0,0,0.1)"></a>')
                        : 'Belum ada bukti transfer')
                    ->columnSpanFull(),
                TextInput::make('bank_name')
                    ->label('Nama Bank')
                    ->helperText('Contoh: BCA, Mandiri, BRI.'),
                TextInput::make('account_name')
                    ->label('Nama Rekening')
                    ->helperText('Nama pemilik rekening pengirim.'),
                TextInput::make('account_number')
                    ->label('No. Rekening')
                    ->helperText('Nomor rekening pengirim.'),
                DateTimePicker::make('paid_at')
                    ->label('Dibayar Pada')
                    ->helperText('Tanggal & waktu pembayaran dilakukan.'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull()
                    ->helperText('Catatan tambahan terkait pembayaran.'),
            ]);
    }
}
