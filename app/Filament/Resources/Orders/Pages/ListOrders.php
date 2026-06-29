<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua')
                ->badge(fn () => Order::count()),
            'pending' => Tab::make('Pending')
                ->badge(fn () => Order::where('status', 'pending')->count())
                ->badgeColor('warning')
                ->query(fn ($query) => $query->where('status', 'pending')),
            'processing' => Tab::make('Processing')
                ->badge(fn () => Order::where('status', 'processing')->count())
                ->badgeColor('info')
                ->query(fn ($query) => $query->where('status', 'processing')),
            'shipped' => Tab::make('Dikirim')
                ->badge(fn () => Order::where('status', 'shipped')->count())
                ->badgeColor('primary')
                ->query(fn ($query) => $query->where('status', 'shipped')),
            'delivered' => Tab::make('Selesai')
                ->badge(fn () => Order::where('status', 'delivered')->count())
                ->badgeColor('success')
                ->query(fn ($query) => $query->where('status', 'delivered')),
            'cancelled' => Tab::make('Dibatalkan')
                ->badge(fn () => Order::where('status', 'cancelled')->count())
                ->badgeColor('danger')
                ->query(fn ($query) => $query->where('status', 'cancelled')),
        ];
    }
}
