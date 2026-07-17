<?php

namespace App\Filament\Widgets;

use App\Models\ActivityTimeline;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;

class ActivityTimelineWidget extends BaseTableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'admin';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityTimeline::with('user')->latest('created_at')->limit(20)
            )
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),
                TextColumn::make('description')
                    ->label('Aktivitas')
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, '_created') => 'success',
                        str_contains($state, '_updated') => 'warning',
                        str_contains($state, '_deleted') => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match (true) {
                        str_contains($state, '_created') => 'Dibuat',
                        str_contains($state, '_updated') => 'Diupdate',
                        str_contains($state, '_deleted') => 'Dihapus',
                        default => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('H:i'),
            ]);
    }
}
