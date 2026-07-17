<?php

namespace App\Filament\Resources\ProductBundles\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ProductBundleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Informasi Bundle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Bundle')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', str($state)->slug()))
                            ->helperText('Nama paket bundle yang tampil di toko'),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('URL slug, auto-generated dari nama'),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull()
                            ->maxLength(500)
                            ->helperText('Deskripsi singkat bundle untuk pelanggan'),
                        TextInput::make('bundle_price')
                            ->label('Harga Bundle')
                            ->prefix('Rp')
                            ->required()
                            ->helperText('Titik (.) otomatis muncul.')
                            ->formatStateUsing(fn ($state): string => $state !== null && $state !== '' ? number_format((float) $state, 0, ',', '.') : '')
                            ->dehydrateStateUsing(fn ($state): ?int => $state !== null && $state !== '' ? (int) str_replace('.', '', $state) : null),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktifkan untuk menyembunyikan bundle dari toko'),
                        DateTimePicker::make('start_time')
                            ->label('Mulai')
                            ->seconds(false)
                            ->helperText('Waktu bundle mulai tampil di toko (opsional)'),
                        DateTimePicker::make('end_time')
                            ->label('Selesai')
                            ->seconds(false)
                            ->after('start_time')
                            ->helperText('Waktu bundle berakhir (opsional)'),
                        FileUpload::make('image')
                            ->label('Gambar Bundle')
                            ->image()
                            ->maxSize(1024)
                            ->directory('bundles')
                            ->helperText('Gambar utama bundle (maks 1MB)'),
                    ]),
                Section::make('Produk dalam Bundle')
                    ->schema([
                        Repeater::make('products')
                            ->columns(3)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Produk')
                                    ->options(fn () => Product::where('is_active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn ($product) => [
                                            $product->id => $product->name.' — Rp '.number_format($product->price, 0, ',', '.'),
                                        ]))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn ($state, $set) => $set(
                                        'unit_price',
                                        Product::find($state)?->price ?? 0
                                    ))
                                    ->helperText('Pilih produk beserta harga normalnya'),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(true)
                                    ->helperText('Jumlah unit dalam bundle'),
                                TextInput::make('unit_price')
                                    ->hidden()
                                    ->default(0),
                            ])
                            ->addActionLabel('Tambah Produk')
                            ->defaultItems(1)
                            ->minItems(1),
                    ]),
                Section::make('Estimasi Harga Bundle')
                    ->description('Total Harga Normal = jumlah dari (harga produk × qty). Diskon = potongan harga yang diberikan ke customer.')
                    ->columnSpanFull()
                    ->columns(5)
                    ->schema([
                        Placeholder::make('total_normal')
                            ->label('Total Harga Normal')
                            ->extraAttributes(['style' => 'font-size:1.1rem'])
                            ->content(function ($get) {
                                $products = $get('products') ?? [];
                                $total = collect($products)->sum(fn ($p) => ($p['quantity'] ?? 1) * ($p['unit_price'] ?? 0));

                                return new HtmlString("<span style='font-size:1.1rem;font-weight:600;'>Rp ".number_format($total, 0, ',', '.').'</span>');
                            }),
                        Placeholder::make('bundle_price_show')
                            ->label('Harga Bundle')
                            ->extraAttributes(['style' => 'font-size:1.1rem'])
                            ->content(function ($get) {
                                $raw = $get('bundle_price') ?? 0;
                                $price = (int) (is_string($raw) ? str_replace('.', '', $raw) : $raw);

                                return new HtmlString("<span style='font-size:1.1rem;font-weight:600;'>Rp ".number_format($price, 0, ',', '.').'</span>');
                            }),
                        Placeholder::make('potongan')
                            ->label('Potongan (Diskon)')
                            ->extraAttributes(['style' => 'font-size:1.1rem'])
                            ->content(function ($get) {
                                $products = $get('products') ?? [];
                                $totalNormal = collect($products)->sum(fn ($p) => ($p['quantity'] ?? 1) * ($p['unit_price'] ?? 0));
                                $rawBundle = $get('bundle_price') ?? 0;
                                $bundlePrice = (int) (is_string($rawBundle) ? str_replace('.', '', $rawBundle) : $rawBundle);
                                $diskon = $totalNormal - $bundlePrice;
                                if ($totalNormal <= 0) {
                                    return new HtmlString("<span style='color:var(--gray-500);'>-</span>");
                                }
                                if ($diskon > 0) {
                                    return new HtmlString("<span style='color:var(--success-600);font-weight:700;font-size:1.1rem;'>Rp ".number_format($diskon, 0, ',', '.').'</span>');
                                }
                                if ($diskon < 0) {
                                    return new HtmlString("<span style='color:var(--danger-600);font-weight:700;font-size:1.1rem;'>Rp ".number_format(abs($diskon), 0, ',', '.').' (lebih mahal)</span>');
                                }

                                return new HtmlString("<span style='font-size:1.1rem;'>Rp 0</span>");
                            }),
                        Placeholder::make('persentase')
                            ->label('Diskon %')
                            ->extraAttributes(['style' => 'font-size:1.1rem'])
                            ->content(function ($get) {
                                $products = $get('products') ?? [];
                                $totalNormal = collect($products)->sum(fn ($p) => ($p['quantity'] ?? 1) * ($p['unit_price'] ?? 0));
                                if ($totalNormal <= 0) {
                                    return new HtmlString("<span style='color:var(--gray-500);'>-</span>");
                                }
                                $rawBundle = $get('bundle_price') ?? 0;
                                $bundlePrice = (int) (is_string($rawBundle) ? str_replace('.', '', $rawBundle) : $rawBundle);
                                $diskon = $totalNormal - $bundlePrice;
                                $pct = ($diskon / $totalNormal) * 100;
                                if ($pct > 0) {
                                    $pctFloor = floor($pct * 100) / 100;
                                    $color = $pctFloor > 50 ? 'var(--warning-600)' : 'var(--success-600)';

                                    return new HtmlString("<span style='color:{$color};font-weight:700;font-size:1.1rem;'>".number_format($pctFloor, 2).'%</span>');
                                }
                                if ($pct < 0) {
                                    $pctFloor = floor(abs($pct) * 100) / 100;

                                    return new HtmlString("<span style='color:var(--danger-600);font-weight:700;font-size:1.1rem;'>".number_format($pctFloor, 2).'% (lebih mahal)</span>');
                                }

                                return new HtmlString("<span style='font-size:1.1rem;'>0%</span>");
                            }),
                        Placeholder::make('status_diskon')
                            ->label('Status')
                            ->extraAttributes(['style' => 'font-size:1.1rem'])
                            ->content(function ($get) {
                                $products = $get('products') ?? [];
                                $totalNormal = collect($products)->sum(fn ($p) => ($p['quantity'] ?? 1) * ($p['unit_price'] ?? 0));
                                $rawBundle = $get('bundle_price') ?? 0;
                                $bundlePrice = (int) (is_string($rawBundle) ? str_replace('.', '', $rawBundle) : $rawBundle);
                                $diskon = $totalNormal - $bundlePrice;
                                $pct = $totalNormal > 0 ? ($diskon / $totalNormal) * 100 : 0;
                                $pctFloor = floor($pct * 100) / 100;
                                $pctDisplay = number_format($pctFloor, 2);
                                if ($totalNormal <= 0) {
                                    return new HtmlString("<span style='color:var(--gray-500);'>Pilih produk & isi harga bundle</span>");
                                }
                                if ($diskon > 0) {
                                    if ($pctFloor > 90) {
                                        return new HtmlString("<span style='color:var(--danger-600);font-weight:700;font-size:1.1rem;'>⚠️ Diskon terlalu besar ({$pctDisplay}%)</span>");
                                    }
                                    if ($pctFloor > 50) {
                                        return new HtmlString("<span style='color:var(--warning-600);font-weight:700;font-size:1.1rem;'>⚠️ Diskon cukup besar ({$pctDisplay}%)</span>");
                                    }

                                    return new HtmlString("<span style='color:var(--success-600);font-weight:700;font-size:1.1rem;'>✅ Diskon {$pctDisplay}%</span>");
                                }
                                if ($diskon < 0) {
                                    return new HtmlString("<span style='color:var(--danger-600);font-weight:700;font-size:1.1rem;'>⚠️ Lebih mahal dari harga normal</span>");
                                }

                                return new HtmlString("<span style='font-size:1.1rem;'>Rp 0 (sama saja)</span>");
                            }),
                    ]),
            ]);
    }
}
