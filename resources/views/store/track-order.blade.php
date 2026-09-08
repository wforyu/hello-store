@extends('layouts.store')

@section('title', 'Lacak Pesanan')

@section('content')
    <div class="max-w-md mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Lacak Pesanan</h1>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 shadow-sm">
            <p class="text-sm text-gray-500 mb-6">
                Masukkan <strong>No. Pesanan</strong> dan <strong>email</strong> yang dipakai saat checkout
                (untuk pesanan tanpa login) untuk melihat status pesanan Anda.
            </p>

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('track.order.lookup') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. Pesanan</label>
                    <input type="text" name="order_number" value="{{ old('order_number') }}" required
                        placeholder="Contoh: ORD-AB12CD34"
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Pemesanan</label>
                    <input type="email" name="guest_email" value="{{ old('guest_email') }}" required placeholder="email@example.com"
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3.5 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Lacak Pesanan
                </button>
            </form>

            @auth
                <div class="mt-6 pt-5 border-t border-gray-100 text-center">
                    <p class="text-sm text-gray-500 mb-3">Punya akun? Lihat semua pesanan Anda.</p>
                    <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-amber-600 hover:text-amber-700 transition">
                        Pesanan Saya →
                    </a>
                </div>
            @endauth
        </div>
    </div>
@endsection