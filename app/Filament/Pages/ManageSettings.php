<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $title = 'Pengaturan Toko';

    protected static ?string $navigationLabel = 'Pengaturan Toko';

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'store_address' => Setting::get('store_address'),
            'phone' => Setting::get('phone'),
            'whatsapp' => Setting::get('whatsapp'),
            'email' => Setting::get('email'),
            'instagram' => Setting::get('instagram'),
            'facebook' => Setting::get('facebook'),
            'tiktok' => Setting::get('tiktok'),
            'bank_accounts' => Setting::get('bank_accounts'),
            'ppn_enabled' => Setting::get('ppn_enabled', '0') === '1',
            'ppn_percentage' => Setting::get('ppn_percentage', '11'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Toko')
                    ->description('Alamat dan kontak utama toko')
                    ->schema([
                        Textarea::make('store_address')
                            ->label('Alamat Toko')
                            ->rows(3)
                            ->helperText('Alamat lengkap toko yang akan ditampilkan di footer'),
                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->helperText('Nomor telepon yang bisa dihubungi'),
                        TextInput::make('whatsapp')
                            ->label('Nomor WhatsApp')
                            ->helperText('Nomor WhatsApp dengan kode negara (contoh: 628123456789)'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->helperText('Alamat email toko'),
                    ])
                    ->columns(2),
                Section::make('Media Sosial')
                    ->description('Link sosial media toko')
                    ->schema([
                        TextInput::make('instagram')
                            ->label('Instagram')
                            ->url()
                            ->helperText('URL lengkap profil Instagram'),
                        TextInput::make('facebook')
                            ->label('Facebook')
                            ->url()
                            ->helperText('URL lengkap halaman Facebook'),
                        TextInput::make('tiktok')
                            ->label('TikTok')
                            ->url()
                            ->helperText('URL lengkap profil TikTok'),
                    ])
                    ->columns(2),
                Section::make('Pengaturan Pajak')
                    ->description('Pengaturan PPN untuk transaksi POS dan toko online')
                    ->schema([
                        Toggle::make('ppn_enabled')
                            ->label('Aktifkan PPN')
                            ->helperText('Jika diaktifkan, PPN akan dikenakan pada transaksi POS dan checkout toko online'),
                        TextInput::make('ppn_percentage')
                            ->label('Persentase PPN')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(11)
                            ->helperText('Persentase PPN yang berlaku (contoh: 11)'),
                    ])
                    ->columns(2),
                Section::make('Rekening Bank')
                    ->description('Nomor rekening yang ditampilkan untuk pembayaran transfer')
                    ->schema([
                        Repeater::make('bank_accounts')
                            ->label('')
                            ->schema([
                                TextInput::make('bank_name')
                                    ->label('Nama Bank')
                                    ->required()
                                    ->helperText('Contoh: BCA, Mandiri, BRI'),
                                TextInput::make('account_number')
                                    ->label('Nomor Rekening')
                                    ->required()
                                    ->helperText('Nomor rekening tujuan transfer'),
                                TextInput::make('account_holder')
                                    ->label('Atas Nama')
                                    ->required()
                                    ->helperText('Nama pemilik rekening'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Tambah Rekening')
                            ->reorderable(false)
                            ->defaultItems(4)
                            ->maxItems(10),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'bank_accounts') {
                $value = json_encode($value);
            }
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()
            ->title('Pengaturan berhasil disimpan')
            ->success()
            ->send();
    }
}
