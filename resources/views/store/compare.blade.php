@extends('layouts.store')

@section('title', 'Bandingkan Produk')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Bandingkan Produk</h1>
        <p class="text-sm text-gray-500 mt-1">Bandingkan hingga 4 produk sekaligus</p>
    </div>

    @if($products->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-500 w-40">Spesifikasi</th>
                        @foreach($products as $product)
                            <th class="text-center py-4 px-4 min-w-[200px]">
                                <div class="relative">
                                    <form action="{{ route('products.compare.toggle', $product['id']) }}" method="POST" class="absolute top-0 right-0">
                                        @csrf
                                        <button type="submit" class="p-1 text-gray-400 hover:text-red-500 transition" title="Hapus">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </form>
                                    <img src="{{ $product['image'] ?: 'https://placehold.co/200x200?text=No+Image' }}"
                                        alt="{{ $product['name'] }}"
                                        class="w-32 h-32 object-cover rounded-xl mx-auto mb-3">
                                    <a href="{{ route('products.show', $product['slug']) }}" class="text-sm font-semibold text-gray-900 hover:text-amber-600 transition block">
                                        {{ $product['name'] }}
                                    </a>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Harga</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center">
                                <span class="text-base font-bold text-gray-900">Rp {{ number_format($product['price'], 0, ',', '.') }}</span>
                                @if($product['compare_price'] && $product['compare_price'] > $product['price'])
                                    <span class="block text-xs text-gray-400 line-through">Rp {{ number_format($product['compare_price'], 0, ',', '.') }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Stok</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center">
                                <span class="text-sm {{ $product['stock'] > 0 ? 'text-emerald-600 font-medium' : 'text-red-500' }}">
                                    {{ $product['stock'] > 0 ? $product['stock'].' tersedia' : 'Habis' }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">SKU</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center text-sm text-gray-700">{{ $product['sku'] ?? '-' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Berat</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center text-sm text-gray-700">{{ $product['weight'] ? $product['weight'].' gr' : '-' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Kategori</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center text-sm text-gray-700">{{ $product['category'] ?? '-' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Rating</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center">
                                @if($product['rating'] > 0)
                                    <span class="text-sm text-amber-500 font-medium">{{ number_format($product['rating'], 1) }}</span>
                                    <span class="text-xs text-gray-400">({{ $product['review_count'] }})</span>
                                @else
                                    <span class="text-sm text-gray-400">Belum ada rating</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @if($products->first()['attributes'] && $products->first()['attributes']->count() > 0)
                        <tr>
                            <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Atribut</td>
                            @foreach($products as $product)
                                <td class="py-3 px-4 text-center text-sm text-gray-700">
                                    @if($product['attributes'] && $product['attributes']->count() > 0)
                                        @foreach($product['attributes'] as $type => $values)
                                            <div class="text-xs">
                                                <span class="font-medium text-gray-500">{{ ucfirst($type) }}:</span>
                                                {{ $values }}
                                            </div>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endif
                    <tr>
                        <td class="py-3 px-6 text-sm font-medium text-gray-500 bg-gray-50/50">Deskripsi</td>
                        @foreach($products as $product)
                            <td class="py-3 px-4 text-center text-sm text-gray-600 max-w-xs">
                                {{ Str::limit(strip_tags($product['description'] ?? ''), 150) }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-amber-600 hover:text-amber-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Produk Lain
            </a>
        </div>
    @else
        <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Belum Ada Produk</h2>
            <p class="text-sm text-gray-400 mb-6">Tambahkan produk untuk mulai membandingkan.</p>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 transition shadow-sm">
                Lihat Produk
            </a>
        </div>
    @endif
</div>
@endsection
