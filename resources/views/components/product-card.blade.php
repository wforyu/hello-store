@props(['product'])

<a href="{{ route('products.show', $product->slug) }}" class="group relative bg-white rounded-2xl border border-gray-100 overflow-hidden hover:border-amber-200 hover:shadow-lg hover:shadow-amber-100/30 transition-all duration-300">
    {{-- Discount Badge --}}
    @if($product->compare_price && $product->compare_price > $product->price)
        <div class="absolute top-2 left-2 z-10 bg-gradient-to-r from-red-500 to-rose-500 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg shadow-sm">
            -{{ round((1 - $product->price / $product->compare_price) * 100) }}%
        </div>
    @endif

    {{-- Featured Badge --}}
    @if($product->featured)
        <div class="absolute top-2 right-2 z-10 bg-gradient-to-r from-amber-400 to-orange-400 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg shadow-sm flex items-center gap-0.5">
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        </div>
    @endif

    {{-- Image --}}
    <div class="aspect-square bg-gradient-to-b from-gray-50 to-gray-100 flex items-center justify-center p-5 relative overflow-hidden">
        @if($product->productImages?->isNotEmpty())
            <img src="{{ $product->main_image }}" alt="{{ $product->name }}"
                class="max-w-full max-h-full object-contain group-hover:scale-110 transition-transform duration-500">
        @else
            <svg class="h-16 w-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        @endif

        {{-- Quick View Overlay --}}
        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300 flex items-end justify-center pb-3 opacity-0 group-hover:opacity-100">
            <span class="bg-white/90 backdrop-blur-sm text-amber-700 text-xs font-semibold px-4 py-2 rounded-xl shadow-sm translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                Lihat Detail
            </span>
        </div>
    </div>

    {{-- Info --}}
    <div class="p-3.5 lg:p-4">
        <h3 class="text-sm font-medium text-gray-900 truncate group-hover:text-amber-700 transition-colors">{{ $product->name }}</h3>

        {{-- Price --}}
        <div class="flex items-baseline gap-1.5 mt-1.5">
            <span class="text-amber-600 font-bold text-sm lg:text-base">Rp{{ number_format($product->price, 0, ',', '.') }}</span>
            @if($product->compare_price && $product->compare_price > $product->price)
                <span class="text-xs text-gray-400 line-through">Rp{{ number_format($product->compare_price, 0, ',', '.') }}</span>
            @endif
        </div>

        {{-- Rating --}}
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
</a>
