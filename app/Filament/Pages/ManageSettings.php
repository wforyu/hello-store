<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
            'whatsapp_text' => Setting::get('whatsapp_text'),
            'email' => Setting::get('email'),
            'instagram' => Setting::get('instagram'),
            'facebook' => Setting::get('facebook'),
            'tiktok' => Setting::get('tiktok'),
            'bank_accounts' => Setting::get('bank_accounts'),
            'ppn_enabled' => Setting::get('ppn_enabled', '0') === '1',
            'ppn_percentage' => Setting::get('ppn_percentage', '11'),
            'logo' => Setting::get('logo'),
            'favicon' => Setting::get('favicon'),
            'smtp_host' => Setting::get('smtp_host'),
            'smtp_port' => Setting::get('smtp_port'),
            'smtp_username' => Setting::get('smtp_username'),
            'smtp_password' => Setting::get('smtp_password'),
            'smtp_encryption' => Setting::get('smtp_encryption', 'tls'),
            'smtp_from_address' => Setting::get('smtp_from_address'),
            'smtp_from_name' => Setting::get('smtp_from_name'),
            'google_analytics_id' => Setting::get('google_analytics_id'),
            'facebook_pixel_id' => Setting::get('facebook_pixel_id'),
            'head_scripts' => Setting::get('head_scripts'),
            'body_scripts' => Setting::get('body_scripts'),
            'points_rate' => Setting::get('points_rate', '10'),
            'points_max_redeem' => Setting::get('points_max_redeem', '50'),
            'social_follow_enabled' => Setting::get('social_follow_enabled', '0') === '1',
            'social_follow_rules' => Setting::get('social_follow_rules', []),
            'mobile_api_url' => Setting::get('mobile_api_url', ''),
            'firebase_service_account' => Setting::get('firebase_service_account', ''),
            'show_sliders' => Setting::get('show_sliders', '1') === '1',
            'show_categories' => Setting::get('show_categories', '1') === '1',
            'show_featured' => Setting::get('show_featured', '1') === '1',
            'show_latest' => Setting::get('show_latest', '1') === '1',
            'show_flash_sale' => Setting::get('show_flash_sale', '1') === '1',
            'show_brands' => Setting::get('show_brands', '1') === '1',
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
                Section::make('Poin & Member Tier')
                    ->description('Pengaturan sistem poin dan tier member otomatis')
                    ->schema([
                        TextInput::make('points_rate')
                            ->label('Persentase Poin')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(10)
                            ->helperText('Persentase dari total belanja yang menjadi poin (contoh: 10 = 10% dari Rp100.000 = 10.000 poin).'),
                        TextInput::make('points_max_redeem')
                            ->label('Maksimal Tukar Poin')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(50)
                            ->helperText('Maksimal persentase total belanja yang bisa dibayar pakai poin (contoh: 50 = max 50% total).'),
                        Placeholder::make('member_tiers_info')
                            ->label('Tier Member & Threshold')
                            ->content(function () {
                                $tiers = User::getSegmentThresholds();
                                $lines = [];
                                foreach ($tiers as $tier => $threshold) {
                                    $lines[] = ucfirst($tier).': belanja minimal Rp'.number_format($threshold, 0, ',', '.');
                                }

                                return implode("\n", $lines);
                            })
                            ->helperText('Tier otomatis berdasarkan total belanja customer. Diskon: Silver 5%, Gold 10%, Platinum 15%, Diamond 20%.'),
                    ])
                    ->columns(2),
                Section::make('Social Follow Rewards')
                    ->description('Reward untuk customer yang follow media sosial toko')
                    ->schema([
                        Toggle::make('social_follow_enabled')
                            ->label('Aktifkan Fitur Social Follow Rewards')
                            ->helperText('Jika diaktifkan, customer bisa claim reward dengan follow media sosial toko'),
                        Repeater::make('social_follow_rules')
                            ->label('')
                            ->schema([
                                Select::make('platform')
                                    ->label('Platform')
                                    ->options([
                                        'instagram' => 'Instagram',
                                        'tiktok' => 'TikTok',
                                    ])
                                    ->required()
                                    ->reactive()
                                    ->helperText('Pilih platform sosial media'),
                                TextInput::make('url')
                                    ->label('URL Profil')
                                    ->url()
                                    ->required()
                                    ->helperText('Link ke profil toko di platform ini'),
                                Select::make('reward_tier')
                                    ->label('Reward Tier')
                                    ->options([
                                        'silver' => 'Silver (5% diskon)',
                                        'gold' => 'Gold (10% diskon)',
                                        'platinum' => 'Platinum (15% diskon)',
                                        'diamond' => 'Diamond (20% diskon)',
                                    ])
                                    ->required()
                                    ->helperText('Tier yang didapat customer setelah follow'),
                                TextInput::make('reward_points')
                                    ->label('Bonus Poin')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Poin tambahan yang didapat (0 = tanpa poin bonus)'),
                                TextInput::make('message')
                                    ->label('Pesan untuk Customer')
                                    ->placeholder('Follow Instagram kami dan dapatkan reward!')
                                    ->helperText('Pesan yang ditampilkan ke customer (opsional)'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Tambah Platform')
                            ->reorderable(false)
                            ->defaultItems(2)
                            ->maxItems(5),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Mobile App')
                    ->description('Pengaturan API URL untuk aplikasi mobile')
                    ->schema([
                        TextInput::make('mobile_api_url')
                            ->label('API URL')
                            ->url()
                            ->placeholder('https://domain-anda.com')
                            ->helperText('URL API server yang dipakai mobile app. Ganti ini saat pindah hosting/VPS. Mobile app akan auto-fetch URL ini dari server.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Push Notifications (Firebase)')
                    ->description('Konfigurasi Firebase untuk push notification ke mobile app')
                    ->schema([
                        Textarea::make('firebase_service_account')
                            ->label('Firebase Service Account JSON')
                            ->placeholder('Paste isi file service-account.json dari Firebase Console')
                            ->helperText('Download dari Firebase Console > Project Settings > Service Accounts > Generate New Private Key. Paste seluruh isi JSON di sini. Diperlukan untuk push notification.')
                            ->rows(8)
                            ->columnSpanFull(),
                        Placeholder::make('firebase_setup_guide')
                            ->label('Cara Setup')
                            ->content(function () {
                                $steps = "1. Buka Firebase Console: https://console.firebase.google.com\n";
                                $steps .= "2. Pilih project hello-store-37904\n";
                                $steps .= "3. Klik gear icon > Project Settings\n";
                                $steps .= "4. Tab 'Service Accounts'\n";
                                $steps .= "5. Klik 'Generate New Private Key'\n";
                                $steps .= "6. Buka file JSON yang didownload\n";
                                $steps .= '7. Copy paste SEMUA isi JSON ke field di atas';

                                return $steps;
                            })
                            ->helperText('Pastikan Firebase Cloud Messaging aktif di project settings'),
                    ])
                    ->collapsible()
                    ->collapsed(),
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
                Section::make('Toko')
                    ->description('Logo, favicon, dan informasi toko')
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo Toko')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->imageEditor()
                            ->helperText('Upload logo toko (format: JPG, PNG, SVG)'),
                        FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->helperText('Upload favicon (32x32px, format: ICO/PNG)'),
                        TextInput::make('whatsapp')
                            ->label('Nomor WhatsApp')
                            ->placeholder('6281234567890')
                            ->helperText('Format internasional tanpa +, contoh: 6281234567890')
                            ->columnSpan(1),
                        TextInput::make('whatsapp_text')
                            ->label('Teks WhatsApp')
                            ->placeholder('Halo, saya ingin bertanya...')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('SMTP / Email')
                    ->description('Konfigurasi email server')
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.gmail.com')
                            ->columnSpan(1),
                        TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->placeholder('587')
                            ->numeric()
                            ->columnSpan(1),
                        TextInput::make('smtp_username')
                            ->label('SMTP Username')
                            ->placeholder('email@domain.com')
                            ->columnSpan(1),
                        TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->columnSpan(1),
                        Select::make('smtp_encryption')
                            ->label('Enkripsi')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ])
                            ->default('tls')
                            ->columnSpan(1),
                        TextInput::make('smtp_from_address')
                            ->label('Email Pengirim')
                            ->placeholder('noreply@domain.com')
                            ->email()
                            ->columnSpan(1),
                        TextInput::make('smtp_from_name')
                            ->label('Nama Pengirim')
                            ->placeholder('Hello Store')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Section::make('Dekorasi Homepage')
                    ->description('Atur section mana yang tampil di halaman utama toko')
                    ->schema([
                        Toggle::make('show_sliders')
                            ->label('Tampilkan Slider/Carousel')
                            ->helperText('Banner rotasi di bagian atas homepage'),
                        Toggle::make('show_categories')
                            ->label('Tampilkan Grid Kategori')
                            ->helperText('Daftar kategori produk di homepage'),
                        Toggle::make('show_featured')
                            ->label('Tampilkan Produk Unggulan')
                            ->helperText('Section produk yang ditandai sebagai unggulan'),
                        Toggle::make('show_latest')
                            ->label('Tampilkan Produk Terbaru')
                            ->helperText('Section produk terbaru yang baru ditambahkan'),
                        Toggle::make('show_flash_sale')
                            ->label('Tampilkan Flash Sale')
                            ->helperText('Section flash sale yang sedang aktif'),
                        Toggle::make('show_brands')
                            ->label('Tampilkan Brand/Merek')
                            ->helperText('Grid brand di halaman homepage'),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
                Section::make('SEO & Analytics')
                    ->description('Google Analytics, Facebook Pixel, SEO meta')
                    ->schema([
                        Textarea::make('google_analytics_id')
                            ->label('Google Analytics ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Masukkan Measurement ID Google Analytics 4')
                            ->rows(2)
                            ->columnSpan(1),
                        Textarea::make('facebook_pixel_id')
                            ->label('Facebook Pixel ID')
                            ->placeholder('1234567890')
                            ->helperText('Masukkan Facebook Pixel ID')
                            ->rows(2)
                            ->columnSpan(1),
                        Textarea::make('head_scripts')
                            ->label('Kode Head Script')
                            ->placeholder('<meta name="custom" content="...">')
                            ->helperText('Kode yang akan dimasukkan di bagian <head>')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('body_scripts')
                            ->label('Kode Body Script')
                            ->placeholder('<script>console.log("custom script")</script>')
                            ->helperText('Kode yang akan dimasukkan sebelum </body>')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'bank_accounts' || $key === 'social_follow_rules') {
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
