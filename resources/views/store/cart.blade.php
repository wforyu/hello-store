@extends('layouts.store')

@section('title', 'Keranjang')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Keranjang Belanja</h1>
    </div>

    @if($cart->isEmpty())
        <div class="text-center py-20 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="h-24 w-24 mx-auto mb-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
            </svg>
            <p class="text-gray-500 text-lg mb-2">Keranjang belanja masih kosong</p>
            <p class="text-gray-400 text-sm mb-6">Yuk, mulai belanja kebutuhanmu sekarang!</p>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-8 py-3.5 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                Mulai Belanja
            </a>
        </div>
    @else
        <form action="{{ route('cart.update') }}" method="POST">
            @csrf
            <div class="space-y-3 lg:space-y-4">
                @foreach($cart as $item)
                    <div class="bg-white rounded-2xl border border-gray-100 p-4 lg:p-5 flex items-center gap-4 shadow-sm hover:shadow transition">
                        {{-- Image --}}
                        <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gray-50 rounded-xl flex items-center justify-center shrink-0 overflow-hidden">
                            @if($item['image'])
                                <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="max-h-full object-contain">
                            @else
                                <svg class="h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('products.show', $item['slug']) }}" class="text-sm lg:text-base font-semibold text-gray-900 hover:text-amber-600 truncate block transition">
                                {{ $item['name'] }}
                            </a>
                            <p class="text-sm text-amber-600 font-bold mt-0.5">Rp{{ number_format($item['price'], 0, ',', '.') }}</p>
                        </div>

                        {{-- Qty --}}
                        <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden">
                            <button type="button" onclick="this.parentElement.querySelector('input').stepDown(); this.parentElement.querySelector('input').dispatchEvent(new Event('change'))"
                                class="px-2.5 py-2 text-gray-500 hover:bg-gray-100 transition text-sm leading-none">−</button>
                            <input type="number" name="quantity_{{ $item['product_id'] }}" value="{{ $item['quantity'] }}"
                                min="1" max="{{ $item['stock'] }}"
                                class="w-10 lg:w-12 text-center border-x-2 border-gray-200 py-2 text-sm font-semibold focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                onchange="this.form.submit()">
                            <button type="button" onclick="this.parentElement.querySelector('input').stepUp(); this.parentElement.querySelector('input').dispatchEvent(new Event('change'))"
                                class="px-2.5 py-2 text-gray-500 hover:bg-gray-100 transition text-sm leading-none">+</button>
                        </div>

                        {{-- Total --}}
                        <div class="text-right lg:min-w-[110px]">
                            <p class="text-sm lg:text-base font-bold text-gray-900">Rp{{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</p>
                            <form action="{{ route('cart.remove', $item['product_id']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-red-400 hover:text-red-600 transition mt-1 flex items-center justify-end gap-0.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Summary --}}
            <div class="mt-6 lg:mt-8 bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <span class="text-sm text-gray-500">Total Belanja</span>
                        <p class="text-2xl lg:text-3xl font-extrabold text-amber-600">
                            Rp{{ number_format($cart->sum(fn($i) => $i['price'] * $i['quantity']), 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="flex gap-3 w-full sm:w-auto">
                        <button type="submit" class="px-5 py-3 border-2 border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition text-sm font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Update
                        </button>
                        <a href="{{ route('checkout') }}" class="flex-1 sm:flex-none text-center bg-gradient-to-r from-amber-500 to-orange-500 text-white px-8 py-3 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2">
                            Checkout
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    @endif
@endsection
