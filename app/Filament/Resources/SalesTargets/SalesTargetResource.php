<?php

namespace App\Filament\Resources\SalesTargets;

use App\Filament\Resources\SalesTargets\Pages\CreateSalesTarget;
use App\Filament\Resources\SalesTargets\Pages\EditSalesTarget;
use App\Filament\Resources\SalesTargets\Pages\ListSalesTargets;
use App\Models\SalesTarget;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SalesTargetResource extends Resource
{
    protected static ?string $model = SalesTarget::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama Target')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Contoh: Target Juli 2026')
                    ->columnSpanFull(),
                TextInput::make('target_amount')
                    ->label('Target Revenue (Rp)')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->columnSpan(1),
                TextInput::make('target_orders')
                    ->label('Target Orders')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->columnSpan(1),
                DatePicker::make('start_date')
                    ->label('Tanggal Mulai')
                    ->required()
                    ->columnSpan(1),
                DatePicker::make('end_date')
                    ->label('Tanggal Akhir')
                    ->required()
                    ->afterOrEqual('start_date')
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
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('target_amount')
                    ->label('Target')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('current_amount')
                    ->label('Tercapai')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('revenue_progress')
                    ->label('Progress')
                    ->formatStateUsing(function ($record) {
                        $progress = $record->revenue_progress;
                        $color = $progress >= 100 ? '#10b981' : ($progress >= 60 ? '#f59e0b' : '#ef4444');

                        return '<div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; height: 8px; background: var(--gray-700); border-radius: 4px; overflow: hidden;">
                                <div style="width: '.min(100, $progress).'%; height: 100%; background: '.$color.'; border-radius: 4px;"></div>
                            </div>
                            <span style="font-size: 12px; color: var(--gray-400); min-width: 45px; text-align: right;">'.$progress.'%</span>
                        </div>';
                    })
                    ->html()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Berakhir')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->state(fn ($record) => $record->days_remaining.' hari')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => ListSalesTargets::route('/'),
            'create' => CreateSalesTarget::route('/create'),
            'edit' => EditSalesTarget::route('/{record}/edit'),
        ];
    }
}
