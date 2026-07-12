<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diperbarui',
                        'deleted' => 'Dihapus',
                        'restored' => 'Dipulihkan',
                        default => $state,
                    }),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('auditable_type')
                    ->label('Tipe')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
