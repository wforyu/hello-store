@props(['product', 'inWishlist' => false, 'flashSaleMap' => null])

@php
    $flashData = $flashSaleMap?->get($product->id);
    $displayPrice = $flashData ? $flashData['flash_price'] : $product->price;
    $displayCompare = $flashData ? $product->price : ($product->compare_price ?? 0);
    $soldCount = $product->order_items_sum_quantity ?? 0;
@endphp

<div class="group relative bg-white rounded-2xl border border-gray-100 overflow-hidden hover:border-amber-200 hover:shadow-lg hover:shadow-amber-100/30 transition-all duration-300">
    {{-- Wishlist Heart Button --}}
    @auth
        <button @click="fetch('{{ route('wishlist.toggle', $product) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' } }).then(r => r.json()).then(d => { if(d.status === 'added') { $el.querySelector('svg').classList.add('text-red-500'); $el.querySelector('svg').classList.remove('text-gray-400'); $el.querySelector('svg').setAttribute('fill','currentColor'); } else { $el.querySelector('svg').classList.remove('text-red-500'); $el.querySelector('svg').classList.add('text-gray-400'); $el.querySelector('svg').setAttribute('fill','none'); } })"
            onclick="event.preventDefault(); event.stopPropagation();"
            class="absolute top-2 right-2 z-20 w-9 h-9 flex items-center justify-center rounded-full bg-white/80 hover:bg-white shadow-sm transition">
            <svg class="w-5 h-5 {{ $inWishlist ? 'text-red-500' : 'text-gray-400' }}" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
            </svg>
        </button>
    @endauth

    {{-- Compare Button --}}
    <button type="button"
        x-data
        @click.prevent="fetch('{{ route('products.compare.toggle', $product) }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { if(d.success) { window.dispatchEvent(new CustomEvent('compare-updated', {detail: d.count})) } })"
        onclick="event.preventDefault(); event.stopPropagation();"
        class="absolute top-12 right-2 z-20 w-9 h-9 flex items-center justify-center rounded-full bg-white/80 hover:bg-white shadow-sm transition text-gray-400 hover:text-amber-500"
        title="Bandingkan">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
    </button>

    {{-- Flash Sale Badge --}}
    @if($flashData)
        <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-red-600 to-pink-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm flex items-center gap-1">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
            Flash Sale
        </div>
    @elseif($product->compare_price && $product->compare_price > $product->price)
        <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-red-500 to-rose-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm">
            -{{ round((1 - $product->price / $product->compare_price) * 100) }}%
        </div>
    @endif

    {{-- Featured Badge --}}
    @if($product->featured)
        <div class="absolute top-2 right-10 z-10 bg-gradient-to-r from-amber-400 to-orange-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg shadow-sm flex items-center gap-0.5">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
    @endif

    {{-- Image Area --}}
    <a href="{{ route('products.show', $product->slug) }}" class="block aspect-square bg-gradient-to-b from-gray-50 to-gray-100 relative overflow-hidden">
        @if($product->productImages?->isNotEmpty())
            <img src="{{ $product->main_image }}" alt="{{ $product->name }}" loading="lazy"
                class="w-full h-full object-contain p-5 group-hover:scale-110 transition-transform duration-500">
        @else
            <div class="w-full h-full flex items-center justify-center">
                <svg class="h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        @endif

        {{-- Quick Add to Cart Overlay (Shopee-style) --}}
        @if($product->stock > 0)
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-300 flex items-end justify-center pb-3">
                <button type="button"
                    x-data="{ adding: false, added: false }"
                    @click.prevent.stop="
                        if (adding || added) return;
                        adding = true;
                        const fd = new FormData();
                        fd.append('_token', '{{ csrf_token() }}');
                        fd.append('quantity', 1);
                        fetch('{{ route('cart.add', $product) }}', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                            .then(r => r.json())
                            .then(d => { adding = false; if (d.success) { added = true; setTimeout(() => added = false, 1500); window.dispatchEvent(new CustomEvent('cart-updated', { detail: d })); } })
                            .catch(() => { adding = false; window.location.href = '{{ route('products.show', $product->slug) }}'; });
                    "
                    class="bg-white/95 backdrop-blur-sm rounded-xl px-4 py-2 shadow-lg hover:bg-amber-50 transition-all duration-200 flex items-center gap-2"
                    :class="added ? 'bg-emerald-50' : ''">
                    <template x-if="!adding && !added">
                        <span class="flex items-center gap-1.5 text-amber-700 text-xs font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            + Keranjang
                        </span>
                    </template>
                    <template x-if="adding">
                        <svg class="w-4 h-4 text-amber-600 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </template>
                    <template x-if="added">
                        <span class="flex items-center gap-1.5 text-emerald-600 text-xs font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Ditambahkan
                        </span>
                    </template>
                </button>
            </div>
        @endif

        {{-- Quick View Overlay --}}
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300 flex items-end justify-center pb-14 opacity-0 group-hover:opacity-100">
            <span class="bg-white/90 backdrop-blur-sm text-amber-700 text-xs font-semibold px-4 py-2 rounded-xl shadow-sm translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                Lihat Detail
            </span>
        </div>
    </a>

    {{-- Info --}}
    <div class="p-3.5 lg:p-4">
        <a href="{{ route('products.show', $product->slug) }}" class="block">
            <h3 class="text-sm font-medium text-gray-900 truncate group-hover:text-amber-700 transition-colors">{{ $product->name }}</h3>
        </a>
        @if($product->brand)
            <p class="text-[11px] text-gray-400 truncate">{{ $product->brand->name }}</p>
        @endif

        {{-- Price --}}
        <div class="flex items-baseline gap-1.5 mt-1.5">
            <span class="text-amber-600 font-bold text-sm lg:text-base">Rp{{ number_format($displayPrice, 0, ',', '.') }}</span>
            @if($displayCompare > $displayPrice)
                <span class="text-xs text-gray-400 line-through">Rp{{ number_format($displayCompare, 0, ',', '.') }}</span>
            @endif
        </div>

        {{-- Rating + Sold --}}
        <div class="flex items-center gap-1 mt-1.5">
            <div class="flex">
                @for($i = 0; $i < 5; $i++)
                    <svg class="w-3 h-3 {{ $i < ($product->approved_reviews_avg_rating ?? 0) ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                @endfor
            </div>
            @if(($product->approved_reviews_count ?? 0) > 0)
                <span class="text-[10px] text-gray-400">({{ $product->approved_reviews_count }})</span>
            @endif
            @if($soldCount > 0)
                <span class="text-[10px] text-gray-400 ml-auto">{{ number_format($soldCount) }} terjual</span>
            @endif
        </div>

        {{-- Stock indicator --}}
        @if($product->stock > 0 && $product->stock <= 5)
            <p class="text-[10px] text-orange-500 font-medium mt-1">Sisa {{ $product->stock }} unit</p>
        @elseif($product->stock > 5)
            <p class="text-[10px] text-emerald-500 font-medium mt-1"><span class="inline-block w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1"></span>Stok tersedia</p>
        @else
            <p class="text-[10px] text-red-500 font-medium mt-1">Stok habis</p>
        @endif
    </div>
</div>
