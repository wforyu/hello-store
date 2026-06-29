@extends('layouts.store')

@section('title', 'Home')

@section('content')
    {{-- Hero Section --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-amber-500 via-orange-500 to-orange-600 rounded-2xl md:rounded-3xl p-8 md:p-14 mb-8 lg:mb-12">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" viewBox="0 0 600 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="100" cy="100" r="80" fill="white"/>
                <circle cx="500" cy="50" r="100" fill="white"/>
                <circle cx="50" cy="350" r="60" fill="white"/>
                <circle cx="550" cy="300" r="70" fill="white"/>
                <circle cx="300" cy="400" r="90" fill="white"/>
            </svg>
        </div>
        <div class="relative z-10 max-w-2xl">
            <span class="inline-block bg-white/20 text-white text-xs font-semibold px-4 py-1.5 rounded-full mb-4 backdrop-blur-sm">Belanja Hemat &amp; Mudah</span>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white leading-tight mb-4">
                Belanja Kebutuhan <br><span class="text-amber-200">Harian</span> Jadi Lebih Mudah
            </h1>
            <p class="text-white/80 text-sm md:text-base mb-6 max-w-lg">Temukan produk fashion, elektronik, alat tulis, dan kebutuhan sekolah dengan harga terbaik. Gratis ongkir + promo spesial!</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-white text-amber-700 font-bold px-7 py-3.5 rounded-xl hover:bg-amber-50 shadow-lg hover:shadow-xl transition-all">
                    Belanja Sekarang
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
                <a href="{{ route('products.index', ['category' => 'elektronik']) }}" class="inline-flex items-center gap-2 bg-white/10 text-white border border-white/20 px-7 py-3.5 rounded-xl hover:bg-white/20 transition-all backdrop-blur-sm">
                    Lihat Kategori
                </a>
            </div>
        </div>
        {{-- Trust badges --}}
        <div class="relative z-10 mt-8 pt-6 border-t border-white/20 flex flex-wrap gap-5 text-white/70 text-xs">
            <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg> Garansi 30 Hari</span>
            <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Pembayaran Aman</span>
            <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3z"/></svg> Pengiriman Cepat</span>
        </div>
    </div>

    {{-- Categories --}}
    @if($categories->isNotEmpty())
        <section class="mb-8 lg:mb-12">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Kategori</h2>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">Lihat Semua &rarr;</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 lg:gap-4">
                @foreach($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}"
                        class="group relative bg-white rounded-2xl p-5 text-center border border-gray-100 hover:border-amber-200 hover:shadow-lg hover:shadow-amber-100/50 transition-all duration-300">
                        <div class="w-14 h-14 bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl flex items-center justify-center mx-auto mb-3 group-hover:from-amber-100 group-hover:to-orange-100 group-hover:scale-110 transition-all duration-300">
                            @php
                                $icons = ['🛍️', '👔', '👗', '✏️'];
                                $icon = $icons[$loop->index % count($icons)];
                            @endphp
                            <span class="text-2xl">{{ $icon }}</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-800 group-hover:text-amber-700 transition-colors">{{ $category->name }}</h3>
                        @if($category->children->isNotEmpty())
                            <p class="text-xs text-gray-400 mt-0.5">{{ $category->children->count() }} subkategori</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured Products --}}
    @if($featuredProducts->isNotEmpty())
        <section class="mb-8 lg:mb-12">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Produk Unggulan</h2>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">Lihat Semua &rarr;</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
                @foreach($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Latest Products with section header decoration --}}
    <section>
        <div class="flex items-center gap-3 mb-5">
            <div class="h-0.5 flex-1 bg-gradient-to-r from-transparent to-amber-200 rounded-full"></div>
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900 text-center">Produk Terbaru</h2>
            <div class="h-0.5 flex-1 bg-gradient-to-l from-transparent to-amber-200 rounded-full"></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
            @foreach($latestProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
    </section>
@endsection
