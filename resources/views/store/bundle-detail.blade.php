@extends('layouts.store')

@section('title', $bundle->name . ' - Paket Produk')

@section('content')
    <nav class="text-sm text-gray-400 mb-5 flex items-center gap-1.5">
        <a href="{{ route('home') }}" class="hover:text-amber-600 transition">Home</a>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="{{ route('products.bundles') }}" class="hover:text-amber-600 transition">Paket Produk</a>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-600">{{ $bundle->name }}</span>
    </nav>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="grid md:grid-cols-2 gap-0">
            {{-- Image --}}
            <div class="bg-gradient-to-b from-purple-50 to-amber-50 p-4 md:p-8 flex items-center justify-center min-h-[300px] md:min-h-[400px]">
                @if($bundle->image)
                    <img src="{{ Storage::url($bundle->image) }}" alt="{{ $bundle->name }}" class="max-w-full max-h-[350px] object-contain rounded-xl">
                @else
                    <div class="text-center">
                        <svg class="h-32 w-32 text-purple-200 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-purple-300 text-sm mt-2 font-medium">Paket Produk</p>
                    </div>
                @endif
            </div>

            {{-- Info --}}
            <div class="p-6 md:p-8 lg:p-10 flex flex-col">
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-gradient-to-r from-purple-100 to-amber-100 text-purple-700 px-3 py-1 rounded-full w-fit mb-3">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Paket Hemat
                </span>

                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">{{ $bundle->name }}</h1>

                <div class="flex items-center gap-3 text-sm text-gray-400 mb-4">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        {{ $bundle->products_count }} produk
                    </span>
                    @if($bundle->start_time)
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $bundle->start_time->format('d M Y') }} - {{ $bundle->end_time ? $bundle->end_time->format('d M Y') : 'Selesai' }}
                        </span>
                    @endif
                </div>

                @if($bundle->description)
                    <p class="text-sm text-gray-600 mb-6 leading-relaxed">{{ $bundle->description }}</p>
                @endif

                {{-- Price --}}
                <div class="mb-6 p-4 bg-amber-50 rounded-xl">
                    <div class="flex items-baseline gap-3 mb-1">
                        <span class="text-3xl lg:text-4xl font-extrabold text-amber-600">Rp{{ number_format($bundle->bundle_price, 0, ',', '.') }}</span>
                        @if($bundle->total_original_price > $bundle->bundle_price)
                            <span class="text-lg text-gray-400 line-through">Rp{{ number_format($bundle->total_original_price, 0, ',', '.') }}</span>
                        @endif
                    </div>
                    @if($bundle->total_original_price > $bundle->bundle_price)
                        @php $saving = $bundle->total_original_price - $bundle->bundle_price; @endphp
                        <span class="inline-flex items-center gap-1 text-sm font-bold text-red-500 bg-red-50 px-3 py-1 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            Hemat Rp{{ number_format($saving, 0, ',', '.') }} ({{ round($saving / $bundle->total_original_price * 100) }}%)
                        </span>
                    @endif
                </div>

                {{-- Add to Cart --}}
                <form action="{{ route('cart.add-bundle', $bundle) }}" method="POST" class="mt-auto">
                    @csrf
                    <button type="submit" class="w-full bg-gradient-to-r from-purple-500 to-amber-500 text-white px-6 py-3.5 rounded-xl font-bold hover:from-purple-600 hover:to-amber-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        Tambah Paket ke Keranjang
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Products in Bundle --}}
    <div class="mt-8 lg:mt-12">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Isi Paket</h2>
        </div>
        <div class="space-y-3">
            @foreach($bundle->products as $product)
                <a href="{{ route('products.show', $product->slug) }}" class="flex items-center gap-4 bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:shadow-md transition group">
                    <div class="w-16 h-16 lg:w-20 lg:h-20 bg-gray-50 rounded-xl overflow-hidden shrink-0">
                        @if($product->productImages->isNotEmpty())
                            <img src="{{ $product->main_image }}" alt="{{ $product->name }}" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm lg:text-base font-semibold text-gray-900 group-hover:text-amber-600 transition truncate">{{ $product->name }}</h3>
                        @if($product->brand)
                            <p class="text-xs text-gray-400 mt-0.5">{{ $product->brand->name }}</p>
                        @endif
                        <div class="flex items-center gap-2 mt-1">
                            @if($product->approved_reviews_count > 0)
                                <div class="flex items-center gap-1">
                                    <svg class="w-3 h-3 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    <span class="text-xs text-gray-400">{{ number_format($product->approved_reviews_avg_rating, 1) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-xs text-gray-400">Qty: {{ $product->pivot->quantity }}</p>
                        <p class="text-sm font-bold text-gray-900 mt-0.5">Rp{{ number_format($product->price, 0, ',', '.') }}</p>
                        @if($product->stock > 0)
                            <p class="text-[10px] text-emerald-600 mt-0.5">Stok: {{ $product->stock }}</p>
                        @else
                            <p class="text-[10px] text-red-500 mt-0.5">Stok Habis</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Price Breakdown --}}
    @if($bundle->total_original_price > $bundle->bundle_price)
        <div class="mt-8 bg-gray-50 rounded-2xl p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Rincian Harga</h3>
            <div class="space-y-2">
                @foreach($bundle->products as $product)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $product->pivot->quantity }}x {{ $product->name }}</span>
                        <span class="text-gray-900">Rp{{ number_format($product->price * $product->pivot->quantity, 0, ',', '.') }}</span>
                    </div>
                @endforeach
                <div class="border-t border-gray-200 pt-2 flex justify-between text-sm">
                    <span class="text-gray-500">Total Normal</span>
                    <span class="text-gray-400 line-through">Rp{{ number_format($bundle->total_original_price, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-base font-bold">
                    <span class="text-gray-900">Harga Paket</span>
                    <span class="text-purple-600">Rp{{ number_format($bundle->bundle_price, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm font-semibold text-red-500">
                    <span>Hemat</span>
                    <span>-Rp{{ number_format($bundle->total_original_price - $bundle->bundle_price, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Related Bundles --}}
    @if($relatedBundles->isNotEmpty())
        <section class="mt-10 lg:mt-14">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Paket Lainnya</h2>
                <a href="{{ route('products.bundles') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">Lihat Semua &rarr;</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach($relatedBundles as $rb)
                    <a href="{{ route('products.bundle-detail', $rb->slug) }}" class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition">
                        @if($rb->image)
                            <div class="aspect-video bg-gray-100 overflow-hidden">
                                <img src="{{ Storage::url($rb->image) }}" alt="{{ $rb->name }}" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="aspect-video bg-gradient-to-br from-purple-50 to-amber-50 flex items-center justify-center">
                                <svg class="h-12 w-12 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                        @endif
                        <div class="p-4">
                            <h3 class="font-bold text-gray-900 mb-1">{{ $rb->name }}</h3>
                            <p class="text-xs text-gray-400 mb-2">{{ $rb->products_count ?? $rb->products->count() }} produk</p>
                            <div class="flex items-baseline gap-2">
                                <span class="text-lg font-extrabold text-purple-600">Rp{{ number_format($rb->bundle_price, 0, ',', '.') }}</span>
                                @if($rb->total_original_price > $rb->bundle_price)
                                    <span class="text-xs text-gray-400 line-through">Rp{{ number_format($rb->total_original_price, 0, ',', '.') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="h-20 lg:hidden"></div>
@endsection
