@extends('layouts.account')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-6">Dashboard</h1>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <p class="text-xs text-gray-400 font-medium">Total Pesanan</p>
            </div>
            <p class="text-3xl font-extrabold text-gray-900">{{ $ordersCount }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-xs text-gray-400 font-medium">Pesanan Selesai</p>
            </div>
            <p class="text-3xl font-extrabold text-gray-900">{{ $user->orders()->where('status', 'delivered')->count() }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <p class="text-xs text-gray-400 font-medium">Alamat</p>
            </div>
            <p class="text-3xl font-extrabold text-gray-900">{{ $addressesCount }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <p class="text-xs text-gray-400 font-medium">Ulasan</p>
            </div>
            <p class="text-3xl font-extrabold text-gray-900">{{ $reviewsCount }}</p>
        </div>
    </div>

    {{-- Recent Orders --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Pesanan Terbaru
            </h2>
            @if($ordersCount > 0)
                <a href="{{ route('orders.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">Lihat Semua &rarr;</a>
            @endif
        </div>

        @if($recentOrders->isEmpty())
            <div class="p-10 text-center">
                <svg class="h-16 w-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <p class="text-gray-500 font-medium mb-1">Belum ada pesanan</p>
                <p class="text-gray-400 text-sm mb-4">Ayo belanja sekarang!</p>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm transition text-sm">
                    Mulai Belanja
                </a>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($recentOrders as $order)
                    <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 transition">
                        <div class="min-w-0 mr-4">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $order->order_number }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $order->items->count() }} item &middot; {{ $order->created_at->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-sm font-bold text-amber-600">Rp{{ number_format($order->total, 0, ',', '.') }}</span>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full
                                @if($order->status === 'pending') bg-yellow-50 text-yellow-700
                                @elseif($order->status === 'confirmed') bg-blue-50 text-blue-700
                                @elseif($order->status === 'processing') bg-cyan-50 text-cyan-700
                                @elseif($order->status === 'shipped') bg-purple-50 text-purple-700
                                @elseif($order->status === 'delivered') bg-emerald-50 text-emerald-700
                                @else bg-red-50 text-red-700 @endif">
                                {{ ucfirst($order->status) }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
