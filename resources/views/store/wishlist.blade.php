@extends('layouts.store')

@section('title', 'Wishlist Saya')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Wishlist Saya</h1>

    @if($wishlists->count() > 0)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 lg:gap-6">
            @foreach($wishlists as $product)
                <x-product-card :product="$product" :inWishlist="true" />
            @endforeach
        </div>
        <div class="mt-8">
            {{ $wishlists->links() }}
        </div>
    @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Wishlist Masih Kosong</h2>
            <p class="text-sm text-gray-400 mb-6">Simpan produk favoritmu dengan menekan ikon hati di halaman produk.</p>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-3 rounded-xl font-bold hover:bg-amber-600 transition">
                Mulai Belanja
            </a>
        </div>
    @endif
</div>
@endsection
