<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
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
}
