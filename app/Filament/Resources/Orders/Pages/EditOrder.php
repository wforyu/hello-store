<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Notification;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Cetak Resi')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn () => route('orders.print-admin', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('status') && $this->record->status === 'shipped') {
            Notification::createForUser(
                $this->record->user_id,
                'order',
                'Pesanan #'.$this->record->order_number.' telah dikirim',
                'Nomor resi: '.($this->record->shipping_tracking_number ?? '-'),
                null,
                route('orders.show', $this->record)
            );
        }
    }
}
