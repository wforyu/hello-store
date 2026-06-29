@extends('layouts.store')

@section('title', 'Pesanan Saya')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Pesanan Saya</h1>
    </div>

    @if($orders->isEmpty())
        <div class="text-center py-20 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="h-24 w-24 mx-auto mb-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <p class="text-gray-500 text-lg mb-2">Belum ada pesanan</p>
            <p class="text-gray-400 text-sm mb-6">Ayo belanja sekarang!</p>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-8 py-3.5 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                Mulai Belanja
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <div class="block bg-white rounded-2xl border border-gray-100 p-5 hover:border-amber-200 hover:shadow-md transition-all duration-200 shadow-sm">
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <span class="text-sm font-mono font-semibold text-gray-800">{{ $order->order_number }}</span>
                        </div>
                        <span class="text-xs font-bold px-3 py-1.5 rounded-full
                            @if($order->status === 'pending') bg-yellow-50 text-yellow-700 border border-yellow-200
                            @elseif($order->status === 'confirmed') bg-blue-50 text-blue-700 border border-blue-200
                            @elseif($order->status === 'processing') bg-cyan-50 text-cyan-700 border border-cyan-200
                            @elseif($order->status === 'shipped') bg-purple-50 text-purple-700 border border-purple-200
                            @elseif($order->status === 'delivered') bg-emerald-50 text-emerald-700 border border-emerald-200
                            @elseif($order->status === 'refunded') bg-red-50 text-red-700 border border-red-200
                            @else bg-gray-50 text-gray-700 border border-gray-200 @endif">
                            {{ $order->status === 'refunded' ? 'Diretur' : ucfirst($order->status) }}
                        </span>
                    </a>
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">{{ $order->items->count() }} item</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $order->created_at->format('d M Y, H:i') }}</p>
                        </div>
                        <span class="text-xl font-extrabold text-amber-600">Rp{{ number_format($order->total, 0, ',', '.') }}</span>
                    </a>
                    @if($order->status === 'delivered')
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-end">
                            <form action="{{ route('orders.reorder', $order) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center gap-2 bg-emerald-500 text-white px-4 py-2 rounded-xl font-bold hover:bg-emerald-600 shadow-sm hover:shadow transition text-xs">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                    Beli Lagi
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
