<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Notification;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable()
                    ->width(160),
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('customer_name')
                    ->label('Pelanggan')
                    ->getStateUsing(fn ($record) => $record->customer_name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('guest_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"))),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Diretur',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'danger',
                        'pending' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unpaid' => 'Belum Bayar',
                        'pending' => 'Pending',
                        'paid' => 'Lunas',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('stock_conflict')
                    ->label('Konflik Stok')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Konflik' : '-')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-o-exclamation-triangle' : '')
                    ->description(fn (Order $record): ?string => $record->stock_conflict ? $record->conflict_reason : null)
                    ->toggleable(),
                TextColumn::make('client_ref')
                    ->label('Ref. Client')
                    ->copyable()
                    ->placeholder('POS web')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual_transfer' => 'Transfer',
                        'cod' => 'COD',
                        'cash' => 'Tunai',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipping_courier')
                    ->label('Kurir')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipping_tracking_number')
                    ->label('No. Resi')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipping_cost')
                    ->label('Ongkir')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipped_at')
                    ->label('Dikirim')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')
                    ->label('Diterima')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancelled_at')
                    ->label('Dibatalkan')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('address.id')
                    ->label('Alamat')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('hari_ini')
                    ->label('Hari Ini')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),
                Filter::make('menunggu')
                    ->label('Pending / Processing')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['pending', 'processing'])),
                Filter::make('stock_conflict')
                    ->label('Konflik Stok')
                    ->query(fn (Builder $query): Builder => $query->where('stock_conflict', true)),
                Filter::make('dari_pos_client')
                    ->label('Dari POS Desktop')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('client_id')),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Status Pembayaran')
                    ->options([
                        'unpaid' => 'Belum Bayar',
                        'pending' => 'Pending',
                        'paid' => 'Lunas',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('tandaiKonflikSudahDicek')
                    ->label('Tandai Sudah Dicek')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => (bool) $record->stock_conflict)
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Konflik Stok Sudah Dicek?')
                    ->modalDescription('Alasan konflik akan dipindahkan ke Catatan Admin dan order dikeluarkan dari daftar konflik. Stok dan total tidak diubah.')
                    ->modalSubmitActionLabel('Ya, Sudah Dicek')
                    ->action(function (Order $record): void {
                        $archive = trim(implode("\n", array_filter([
                            '['.now()->format('d M Y H:i').'] Konflik stok ditandai sudah dicek oleh admin.',
                            $record->conflict_reason,
                        ])));

                        $record->update([
                            'stock_conflict' => false,
                            'conflict_reason' => null,
                            'admin_notes' => trim(((string) $record->admin_notes)."\n\n".$archive),
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('bulkMarkProcessing')
                        ->label('Set Processing')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Ubah Status ke Processing')
                        ->modalDescription('Tandai pesanan yang dipilih sebagai sedang diproses.')
                        ->modalSubmitActionLabel('Ya, Proses')
                        ->action(fn ($records) => static::bulkUpdateStatus($records, 'processing')),

                    BulkAction::make('bulkMarkShipped')
                        ->label('Set Shipped')
                        ->icon('heroicon-o-truck')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Ubah Status ke Shipped')
                        ->modalDescription('Tandai pesanan yang dipilih sebagai sudah dikirim.')
                        ->modalSubmitActionLabel('Ya, Kirim')
                        ->action(fn ($records) => static::bulkUpdateStatus($records, 'shipped')),

                    BulkAction::make('bulkMarkDelivered')
                        ->label('Set Delivered')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Ubah Status ke Delivered')
                        ->modalDescription('Tandai pesanan yang dipilih sebagai sudah diterima.')
                        ->modalSubmitActionLabel('Ya, Selesai')
                        ->action(fn ($records) => static::bulkUpdateStatus($records, 'delivered')),
                ]),
            ]);
    }

    public static function bulkUpdateStatus($records, string $newStatus): void
    {
        $validTransitions = [
            'processing' => ['pending'],
            'shipped' => ['processing'],
            'delivered' => ['shipped'],
        ];

        $allowed = $validTransitions[$newStatus] ?? [];
        $updated = 0;
        $skipped = 0;

        foreach ($records as $order) {
            if (! in_array($order->status, $allowed)) {
                $skipped++;

                continue;
            }

            $data = ['status' => $newStatus];
            if ($newStatus === 'shipped') {
                $data['shipped_at'] = now();
            } elseif ($newStatus === 'delivered') {
                $data['delivered_at'] = now();
            }

            $order->update($data);

            Notification::createForUser(
                $order->user_id,
                'order_status',
                'Status Pesanan Diperbarui',
                "Pesanan #{$order->order_number} sekarang berstatus {$newStatus}.",
                null,
                '/orders/'.$order->id
            );

            $updated++;
        }

        $message = "{$updated} pesanan berhasil diupdate.";
        if ($skipped > 0) {
            $message .= " {$skipped} pesanan dilewati (status tidak valid).";
        }

        FilamentNotification::make()
            ->title('Bulk Update Selesai')
            ->body($message)
            ->success()
            ->send();
    }
}
