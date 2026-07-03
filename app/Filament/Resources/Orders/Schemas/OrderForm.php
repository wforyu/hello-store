<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('Pelanggan')
                    ->required()
                    ->helperText('Pilih pelanggan yang membuat pesanan ini.'),
                TextInput::make('order_number')
                    ->label('No. Pesanan')
                    ->required()
                    ->helperText('Nomor unik pesanan. Otomatis dibuat (POS-xxx / ORD-xxx).'),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->helperText('Ubah status sesuai tahap pengiriman.'),
                TextInput::make('subtotal')
                    ->label('Subtotal')
                    ->required()
                    ->prefix('Rp')
                    ->helperText('Total harga produk sebelum ongkir & diskon.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                TextInput::make('shipping_cost')
                    ->label('Ongkos Kirim')
                    ->required()
                    ->prefix('Rp')
                    ->default(0)
                    ->helperText('Biaya pengiriman. Default 0 untuk POS.')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                TextInput::make('total')
                    ->label('Total')
                    ->required()
                    ->prefix('Rp')
                    ->helperText('Jumlah akhir = subtotal + ongkir - diskon (jika ada).')
                    ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                    ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                Select::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->required()
                    ->options([
                        'manual_transfer' => 'Transfer Manual',
                        'cod' => 'COD (Bayar di Tempat)',
                        'cash' => 'Tunai (POS)',
                    ])
                    ->default('manual_transfer')
                    ->helperText('Pilih metode pembayaran yang digunakan pelanggan.'),
                Select::make('payment_status')
                    ->label('Status Pembayaran')
                    ->required()
                    ->options([
                        'unpaid' => 'Unpaid',
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ])
                    ->default('unpaid')
                    ->helperText('unpaid → pending (jika transfer) → paid. POS langsung "paid".'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->columnSpanFull()
                    ->helperText('Catatan dari pelanggan saat checkout.'),
                Textarea::make('admin_notes')
                    ->label('Catatan Admin')
                    ->columnSpanFull()
                    ->helperText('Catatan internal admin (tidak terlihat pelanggan).'),
                Select::make('address_id')
                    ->relationship('address', 'label')
                    ->label('Alamat')
                    ->helperText('Alamat pengiriman pelanggan.'),
                TextInput::make('shipping_courier')
                    ->label('Kurir')
                    ->helperText('Nama kurir & layanan (contoh: JNE - REG).'),
                TextInput::make('shipping_tracking_number')
                    ->label('No. Resi')
                    ->helperText('Masukkan nomor resi setelah paket dikirim.'),
                Section::make('Tracking Pengiriman')
                    ->schema([
                        Repeater::make('trackingEvents')
                            ->relationship()
                            ->columns(3)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'picked_up' => 'Dijemput Kurir',
                                        'in_transit' => 'Dalam Perjalanan',
                                        'sorting' => 'Disortir',
                                        'out_for_delivery' => 'Diantar Kurir',
                                        'delivered' => 'Telah Sampai',
                                        'failed' => 'Gagal Kirim',
                                    ])
                                    ->default('in_transit')
                                    ->required(),
                                TextInput::make('location')
                                    ->label('Lokasi')
                                    ->maxLength(255),
                                DateTimePicker::make('event_time')
                                    ->label('Waktu')
                                    ->required()
                                    ->default(now())
                                    ->seconds(false),
                                Textarea::make('description')
                                    ->label('Deskripsi')
                                    ->columnSpanFull()
                                    ->maxLength(500),
                            ])
                            ->addActionLabel('Tambah Event Tracking')
                            ->defaultItems(0),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
                DateTimePicker::make('shipped_at')
                    ->label('Dikirim Pada')
                    ->helperText('Isi saat paket sudah dikirim ke kurir.'),
                DateTimePicker::make('delivered_at')
                    ->label('Diterima Pada')
                    ->helperText('Isi saat pelanggan sudah menerima paket.'),
                DateTimePicker::make('cancelled_at')
                    ->label('Dibatalkan Pada')
                    ->helperText('Isi jika pesanan dibatalkan.'),
                Section::make('Informasi Pembayaran')
                    ->schema([
                        Placeholder::make('payment_bank')
                            ->label('Bank')
                            ->content(fn ($record) => $record->payment?->bank_name ?? '-'),
                        Placeholder::make('payment_account_name')
                            ->label('Nama Rekening')
                            ->content(fn ($record) => $record->payment?->account_name ?? '-'),
                        Placeholder::make('payment_account_number')
                            ->label('No. Rekening')
                            ->content(fn ($record) => $record->payment?->account_number ?? '-'),
                        Placeholder::make('payment_paid_at')
                            ->label('Dibayar Pada')
                            ->content(fn ($record) => $record->payment?->paid_at?->format('d M Y, H:i') ?? '-'),
                        Placeholder::make('payment_proof_image')
                            ->label('Bukti Transfer')
                            ->content(fn ($record) => $record->payment?->proof_image_url
                                ? new HtmlString('<a href="'.$record->payment->proof_image_url.'" target="_blank"><img src="'.$record->payment->proof_image_url.'" style="max-width:250px;border-radius:12px;border:1px solid #d1d5db;box-shadow:0 1px 4px rgba(0,0,0,0.08)"></a>')
                                : 'Belum ada bukti transfer')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
