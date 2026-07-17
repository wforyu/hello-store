<?php

namespace App\Filament\Resources\Coupons;

use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('template')
                    ->label('Template Kupon')
                    ->options([
                        '' => '— Custom —',
                        'diskon_persen' => 'Diskon Persen',
                        'diskon_nominal' => 'Diskon Nominal',
                        'gratis_ongkir' => 'Gratis Ongkir',
                        'cashback' => 'Cashback',
                        'beli_x_gratis_y' => 'Beli X Gratis Ongkir',
                        'min_order_diskon' => 'Diskon Min. Belanja',
                    ])
                    ->reactive()
                    ->afterStateUpdated(function ($state, $set) {
                        $templates = [
                            'diskon_persen' => [
                                'type' => 'percentage', 'value' => 10, 'min_order' => 50000,
                                'max_discount' => 25000, 'description' => 'Diskon 10% (maks Rp25.000) untuk pembelian minimal Rp50.000',
                            ],
                            'diskon_nominal' => [
                                'type' => 'nominal', 'value' => 15000, 'min_order' => 100000,
                                'max_discount' => null, 'description' => 'Diskon Rp15.000 untuk pembelian minimal Rp100.000',
                            ],
                            'gratis_ongkir' => [
                                'type' => 'nominal', 'value' => 25000, 'min_order' => 0,
                                'max_discount' => null, 'description' => 'Gratis ongkir (potongan ongkir maks Rp25.000)',
                            ],
                            'cashback' => [
                                'type' => 'percentage', 'value' => 5, 'min_order' => 0,
                                'max_discount' => 50000, 'description' => 'Cashback 5% dalam bentuk poin (maks Rp50.000)',
                            ],
                            'min_order_diskon' => [
                                'type' => 'nominal', 'value' => 50000, 'min_order' => 500000,
                                'max_discount' => null, 'description' => 'Diskon Rp50.000 untuk pembelian minimal Rp500.000',
                            ],
                        ];

                        if ($state && isset($templates[$state])) {
                            $t = $templates[$state];
                            $set('type', $t['type']);
                            $set('value', $t['value']);
                            $set('min_order', $t['min_order']);
                            $set('max_discount', $t['max_discount']);
                            $set('description', $t['description']);
                        }
                    })
                    ->helperText('Pilih template atau custom sendiri')
                    ->columnSpanFull(),
                TextInput::make('code')
                    ->label('Kode Kupon')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->helperText('Contoh: HELLO10, DISKON50')
                    ->columnSpan(1),
                TextInput::make('name')
                    ->label('Nama Kupon')
                    ->required()
                    ->maxLength(100)
                    ->columnSpan(1),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull()
                    ->maxLength(500),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'percentage' => 'Persentase (%)',
                        'nominal' => 'Nominal (Rp)',
                    ])
                    ->required()
                    ->default('percentage')
                    ->columnSpan(1),
                TextInput::make('value')
                    ->label('Nilai')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix(fn ($get) => $get('type') === 'percentage' ? '%' : 'Rp')
                    ->columnSpan(1),
                TextInput::make('min_order')
                    ->label('Minimal Belanja (Rp)')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),
                TextInput::make('max_discount')
                    ->label('Maksimal Diskon (Rp)')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Khusus tipe persentase')
                    ->columnSpan(1),
                TextInput::make('usage_limit')
                    ->label('Batas Pemakaian Total')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->helperText('Kosongkan jika tidak terbatas')
                    ->columnSpan(1),
                TextInput::make('usage_per_user')
                    ->label('Batas Per User')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->nullable()
                    ->helperText('Kosongkan jika tidak terbatas')
                    ->columnSpan(1),
                DateTimePicker::make('starts_at')
                    ->label('Mulai Berlaku')
                    ->nullable()
                    ->columnSpan(1),
                DateTimePicker::make('expires_at')
                    ->label('Berakhir')
                    ->nullable()
                    ->columnSpan(1),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'percentage' ? '%' : 'Rp'),
                TextColumn::make('value')
                    ->label('Nilai')
                    ->formatStateUsing(fn ($state, $record) => $record->type === 'percentage' ? $state.'%' : 'Rp '.number_format((float) $state, 0, ',', '.')),
                TextColumn::make('used_count')
                    ->label('Terpakai')
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Berakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
