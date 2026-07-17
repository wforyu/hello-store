@extends('layouts.store')

@section('title', 'Produk')

@section('content')
    <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">

        {{-- Sidebar --}}
        <aside class="lg:w-56 shrink-0">
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm sticky top-24 space-y-5">
                {{-- Kategori --}}
                <div>
                    <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zm6 0a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4z"/></svg>
                        Kategori
                    </h3>
                    <div class="space-y-0.5">
                        <a href="{{ route('products.index') }}"
                            class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition {{ !request('category') ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800' }}">
                            <svg class="w-4 h-4 {{ !request('category') ? 'text-amber-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Semua Produk
                        </a>
                        @foreach($categories as $category)
                            <a href="{{ route('products.index', ['category' => $category->slug, 'sort' => request('sort'), 'search' => request('search'), 'brand' => request('brand')]) }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition {{ request('category') === $category->slug ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800' }}">
                                @php
                                    $icons = ['🛍️', '👔', '👗', '✏️'];
                                    $icon = $icons[$loop->index % count($icons)];
                                @endphp
                                <span class="text-base">{{ $icon }}</span>
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Brand --}}
                @if($brands->isNotEmpty())
                    <div class="pt-4 border-t border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
                            Merek
                        </h3>
                        <div class="space-y-0.5">
                            <a href="{{ route('products.index', ['category' => request('category'), 'sort' => request('sort'), 'search' => request('search')]) }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition {{ !request('brand') ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800' }}">
                                Semua Merek
                            </a>
                            @foreach($brands as $brand)
                                <a href="{{ route('products.index', ['brand' => $brand->slug, 'category' => request('category'), 'sort' => request('sort'), 'search' => request('search')]) }}"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition {{ request('brand') === $brand->slug ? 'bg-amber-50 text-amber-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800' }}">
                                    {{ $brand->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 min-w-0">

            {{-- Search Result Header --}}
            @if(request('search'))
                <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-5 shadow-sm">
                    <p class="text-sm text-gray-500">
                        Hasil pencarian untuk <strong class="text-gray-900">"{{ request('search') }}"</strong>
                        <span class="text-gray-400 ml-1">({{ $products->total() }} ditemukan)</span>
                    </p>
                </div>
            @endif

            {{-- Sort & Filter Bar --}}
            <div class="flex items-center justify-between gap-4 mb-5">
                <p class="text-sm text-gray-500">
                    {{ $products->total() }} produk ditemukan
                </p>
                <div class="flex items-center gap-2">
                    <label for="sort" class="text-sm text-gray-500 hidden sm:inline">Urutkan:</label>
                    <select id="sort" onchange="window.location.href=this.value"
                        class="text-sm border border-gray-200 rounded-xl px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-400">
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'terbaru']) }}" @selected(request('sort', 'terbaru') === 'terbaru')>Terbaru</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'termurah']) }}" @selected(request('sort') === 'termurah')>Termurah</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'termahal']) }}" @selected(request('sort') === 'termahal')>Termahal</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'nama']) }}" @selected(request('sort') === 'nama')>Nama A-Z</option>
                    </select>
                </div>
            </div>

            {{-- Active Filter Chips --}}
            @if(request()->hasAny(['search', 'category', 'brand', 'sort']))
                @php
                    $activeFilters = [];
                    if (request('search')) $activeFilters[] = ['label' => '"' . request('search') . '"', 'url' => request()->fullUrlWithoutQuery('search')];
                    if (request('category')) {
                        $cat = $categories->firstWhere('slug', request('category'));
                        $activeFilters[] = ['label' => $cat?->name ?? request('category'), 'url' => request()->fullUrlWithoutQuery('category')];
                    }
                    if (request('brand')) {
                        $br = $brands->firstWhere('slug', request('brand'));
                        $activeFilters[] = ['label' => $br?->name ?? request('brand'), 'url' => request()->fullUrlWithoutQuery('brand')];
                    }
                    if (request('sort') && request('sort') !== 'terbaru') {
                        $sortLabels = ['termurah' => 'Harga Termurah', 'termahal' => 'Harga Termahal', 'nama' => 'Nama A-Z'];
                        $activeFilters[] = ['label' => $sortLabels[request('sort')] ?? request('sort'), 'url' => request()->fullUrlWithoutQuery('sort')];
                    }
                @endphp
                @if(count($activeFilters) > 0)
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        @foreach($activeFilters as $filter)
                            <a href="{{ $filter['url'] }}"
                                class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 text-xs font-medium px-3 py-1.5 rounded-full border border-amber-200 hover:bg-amber-100 transition">
                                {{ $filter['label'] }}
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endforeach
                        <a href="{{ route('products.index') }}" class="text-xs font-medium text-gray-400 hover:text-red-500 transition">Hapus Semua</a>
                    </div>
                @endif
            @endif

            {{-- Products Grid --}}
            @if($products->isEmpty())
                <div class="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <svg class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="text-gray-500 mb-4">Produk tidak ditemukan</p>
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-3 rounded-xl font-medium hover:bg-amber-600 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        Reset Filter
                    </a>
                </div>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 lg:gap-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" :flashSaleMap="$flashSaleMap" />
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-8">
                    {{ $products->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
