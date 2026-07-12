@extends('layouts.store')

@section('title', 'Paket Produk')

@section('content')
    {{-- Header --}}
    <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-2xl p-6 lg:p-8 text-white mb-8">
        <h1 class="text-2xl lg:text-3xl font-bold mb-2">Paket Produk</h1>
        <p class="text-white/80">Dapatkan harga spesial dengan membeli paket produk pilihan</p>
    </div>

    @if($bundles->isEmpty())
        <div class="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <p class="text-gray-500">Belum ada paket produk tersedia.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($bundles as $bundle)
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                    @if($bundle->image)
                        <div class="aspect-video bg-gray-100 overflow-hidden">
                            <img src="{{ Storage::url($bundle->image) }}" alt="{{ $bundle->name }}" class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="aspect-video bg-gradient-to-br from-amber-50 to-orange-50 flex items-center justify-center">
                            <svg class="h-16 w-16 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                    @endif
                    <div class="p-5">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $bundle->name }}</h3>
                        @if($bundle->description)
                            <p class="text-sm text-gray-500 mb-4 line-clamp-2">{{ $bundle->description }}</p>
                        @endif

                        {{-- Products in bundle --}}
                        <div class="space-y-2 mb-4">
                            @foreach($bundle->products as $bp)
                                <div class="flex items-center gap-3 text-sm">
                                    <div class="w-10 h-10 bg-gray-50 rounded-lg overflow-hidden shrink-0">
                                        @if($bp->productImages->isNotEmpty())
                                            <img src="{{ $bp->main_image }}" alt="{{ $bp->name }}" class="w-full h-full object-contain">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-gray-600">{{ $bp->pivot->quantity }}x {{ $bp->name }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Price --}}
                        <div class="flex items-baseline gap-2 mb-3">
                            <span class="text-2xl font-extrabold text-amber-600">Rp{{ number_format($bundle->bundle_price, 0, ',', '.') }}</span>
                            @if($bundle->total_original_price > $bundle->bundle_price)
                                <span class="text-sm text-gray-400 line-through">Rp{{ number_format($bundle->total_original_price, 0, ',', '.') }}</span>
                                <span class="text-xs font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-lg">Hemat Rp{{ number_format($bundle->total_original_price - $bundle->bundle_price, 0, ',', '.') }}</span>
                            @endif
                        </div>

                        {{-- Add to cart --}}
                        <form action="{{ route('cart.add-bundle', $bundle) }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full py-2.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl transition-colors text-sm">
                                Tambah ke Keranjang
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $bundles->links() }}
        </div>
    @endif
@endsection
