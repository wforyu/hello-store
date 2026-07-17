<x-filament-panels::page>
    <style>
        .pipeline-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px; min-height: 500px; }
        .pipeline-column {
            flex: 0 0 280px; min-width: 280px;
            background: var(--gray-800); border-radius: 12px;
            display: flex; flex-direction: column;
            border: 1px solid var(--gray-700);
        }
        .pipeline-column-header {
            padding: 12px 16px; border-bottom: 1px solid var(--gray-700);
            display: flex; align-items: center; justify-content: space-between;
            font-weight: 600; font-size: 14px; color: var(--gray-200);
        }
        .pipeline-column-count {
            background: var(--gray-700); color: var(--gray-300);
            font-size: 12px; font-weight: 500; padding: 2px 8px;
            border-radius: 9999px;
        }
        .pipeline-column-body { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 8px; }
        .pipeline-card {
            background: var(--gray-900); border: 1px solid var(--gray-700);
            border-radius: 8px; padding: 12px; cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .pipeline-card:hover { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .pipeline-card-order { font-weight: 700; font-size: 13px; color: var(--gray-100); margin-bottom: 4px; }
        .pipeline-card-customer { font-size: 12px; color: var(--gray-400); margin-bottom: 8px; }
        .pipeline-card-total { font-weight: 600; font-size: 14px; color: var(--primary-400); margin-bottom: 6px; }
        .pipeline-card-meta { font-size: 11px; color: var(--gray-500); display: flex; flex-direction: column; gap: 2px; }
        .pipeline-card-items { font-size: 11px; color: var(--gray-400); margin-bottom: 8px; line-height: 1.4; }
        .pipeline-card-actions { display: flex; gap: 6px; margin-top: 8px; }
        .pipeline-card-btn {
            flex: 1; padding: 6px 8px; border-radius: 6px; font-size: 11px;
            font-weight: 600; border: none; cursor: pointer; text-align: center;
            transition: opacity 0.15s;
        }
        .pipeline-card-btn:hover { opacity: 0.85; }
        .pipeline-card-btn-advance { background: var(--primary-600); color: #fff; }
        .pipeline-card-btn-view { background: var(--gray-700); color: var(--gray-300); }
    </style>

    <div class="pipeline-board" x-data="{ loading: false }">
        @foreach(['pending', 'processing', 'shipped', 'delivered'] as $status)
            @php
                $orders = $this->columns[$status] ?? collect();
            @endphp
            <div class="pipeline-column">
                <div class="pipeline-column-header">
                    <span style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50; background: {{ $this->getStatusColor($status) }}; display: inline-block;"></span>
                        {{ $this->getStatusLabel($status) }}
                    </span>
                    <span class="pipeline-column-count">{{ $orders->count() }}</span>
                </div>
                <div class="pipeline-column-body">
                    @forelse($orders as $order)
                        <div class="pipeline-card" x-data="{ processing: false }">
                            <div class="pipeline-card-order">#{{ $order->order_number }}</div>
                            <div class="pipeline-card-customer">{{ $order->user->name ?? '-' }}</div>
                            <div class="pipeline-card-total">Rp {{ number_format($order->total, 0, ',', '.') }}</div>
                            <div class="pipeline-card-items">
                                @foreach($order->items->take(3) as $item)
                                    {{ $item->quantity }}x {{ $item->product_name ?? $item->product?->name ?? '-' }}@if(! $loop->last), @endif
                                @endforeach
                                @if($order->items->count() > 3)
                                    +{{ $order->items->count() - 3 }} lainnya
                                @endif
                            </div>
                            <div class="pipeline-card-meta">
                                <span>{{ $order->created_at->diffForHumans() }}</span>
                                @if($order->payment_method)
                                    <span>{{ match($order->payment_method) {
                                        'manual_transfer' => 'Transfer',
                                        'cod' => 'COD',
                                        'cash' => 'Tunai',
                                        default => $order->payment_method,
                                    } }}</span>
                                @endif
                            </div>
                            <div class="pipeline-card-actions">
                                @if(! in_array($status, ['delivered', 'cancelled', 'refunded']))
                                    <button
                                        class="pipeline-card-btn pipeline-card-btn-advance"
                                        x-on:click="
                                            processing = true;
                                            $wire.advanceOrder({{ $order->id }})
                                        "
                                        x-bind:disabled="processing"
                                    >
                                        <span x-show="!processing">
                                            @if($status === 'pending') Proses →
                                            @elseif($status === 'processing') Kirim →
                                            @elseif($status === 'shipped') Selesai →
                                            @endif
                                        </span>
                                        <span x-show="processing">...</span>
                                    </button>
                                @endif
                                <a
                                    href="{{ route('filament.admin.resources.orders.edit', ['record' => $order->id]) }}"
                                    class="pipeline-card-btn pipeline-card-btn-view"
                                >
                                    Detail
                                </a>
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 24px; color: var(--gray-500); font-size: 13px;">
                            Tidak ada pesanan
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
