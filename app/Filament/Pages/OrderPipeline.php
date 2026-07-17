<?php

namespace App\Filament\Pages;

use App\Models\Notification;
use App\Models\Order;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class OrderPipeline extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string|UnitEnum|null $navigationGroup = 'Pesanan';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Pipeline Pesanan';

    protected static ?string $slug = 'order-pipeline';

    protected static ?string $navigationLabel = 'Pipeline';

    protected string $view = 'filament.pages.order-pipeline';

    public array $columns = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $statuses = ['pending', 'processing', 'shipped', 'delivered'];
        $this->columns = [];

        foreach ($statuses as $status) {
            $orders = Order::where('status', $status)
                ->with(['user', 'items.product'])
                ->orderBy('created_at', 'asc')
                ->get();

            $this->columns[$status] = $orders;
        }
    }

    public function advanceOrder(int $orderId): void
    {
        $order = Order::findOrFail($orderId);

        $nextStatus = match ($order->status) {
            'pending' => 'processing',
            'processing' => 'shipped',
            'shipped' => 'delivered',
            default => null,
        };

        if (! $nextStatus) {
            return;
        }

        $data = ['status' => $nextStatus];
        if ($nextStatus === 'shipped') {
            $data['shipped_at'] = now();
        } elseif ($nextStatus === 'delivered') {
            $data['delivered_at'] = now();
        }

        $order->update($data);

        Notification::createForUser(
            $order->user_id,
            'order_status',
            'Status Pesanan Diperbarui',
            "Pesanan #{$order->order_number} sekarang berstatus {$nextStatus}.",
            null,
            '/orders/'.$order->id
        );

        $this->loadData();
    }

    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu',
            'processing' => 'Diproses',
            'shipped' => 'Dikirim',
            'delivered' => 'Selesai',
            default => $status,
        };
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => '#f59e0b',
            'processing' => '#3b82f6',
            'shipped' => '#8b5cf6',
            'delivered' => '#10b981',
            default => '#6b7280',
        };
    }
}
