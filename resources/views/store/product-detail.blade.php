@extends('layouts.store')

@section('title', $product->meta_title ?? $product->name)

@push('meta')
    <meta name="description" content="{{ $product->meta_description ?? Str::limit(strip_tags($product->description), 160) }}">
    <meta property="og:title" content="{{ $product->meta_title ?? $product->name }}">
    <meta property="og:description" content="{{ $product->meta_description ?? Str::limit(strip_tags($product->description), 160) }}">
    @if($product->productImages->isNotEmpty())
        <meta property="og:image" content="{{ $product->main_image }}">
    @endif
    <meta property="og:type" content="product">
    <meta name="twitter:card" content="summary_large_image">
@endpush

@section('content')
    {{-- Breadcrumb --}}
    <nav class="text-sm text-gray-400 mb-5 flex items-center gap-1.5">
        <a href="{{ route('home') }}" class="hover:text-amber-600 transition">Home</a>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="{{ route('products.index') }}" class="hover:text-amber-600 transition">Produk</a>
        @if($product->category)
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="hover:text-amber-600 transition">{{ $product->category->name }}</a>
        @endif
    </nav>

    @php
        $hasVariants = $product->variants->where('is_active', true)->isNotEmpty();
        $flashProductData = $flashSaleMap?->get($product->id);
        $flashPrice = $flashProductData ? (float) $flashProductData['flash_price'] : null;
        $effectiveBasePrice = $flashPrice ?? (float) $product->price;

        $variantJson = $product->variants->where('is_active', true)->values()->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => (float) ($v->price ?? $effectiveBasePrice),
            'stock' => (int) $v->stock,
            'weight' => (float) ($v->weight ?? $product->weight),
            'image' => $v->image ? (str_starts_with($v->image, 'http') ? $v->image : Storage::url($v->image)) : null,
            'attribute_ids' => $v->attributeValues->pluck('id')->toArray(),
        ]);
        $attrGroups = $product->attributes->groupBy('type');
        $attrJson = $attrGroups->map(fn ($attrs, $type) => $attrs->map(fn ($a) => [
            'id' => $a->id,
            'type' => $a->type,
            'value' => $a->value,
            'label' => $a->label ?? $a->value,
        ])->values());
        $requiredAttrTypes = $attrGroups->keys();
    @endphp

    {{-- Product Detail --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
        x-data="productVariant({
            variants: @json($variantJson),
            attributes: @json($attrJson),
            requiredTypes: @json($requiredAttrTypes),
            hasVariants: @json($hasVariants),
            basePrice: {{ $effectiveBasePrice }},
            baseStock: {{ $product->stock }},
            baseWeight: {{ $product->weight ?? 200 }},
            baseImage: '{{ $product->main_image }}',
            images: {{ $product->productImages->pluck('url')->toJson() }},
        })">
        <div class="grid md:grid-cols-2 gap-0">
            {{-- Image Gallery --}}
            <div class="bg-gradient-to-b from-gray-50 to-gray-100 p-4 md:p-8 flex flex-col items-center justify-center min-h-[300px] md:min-h-[400px]">
                @if($product->productImages->isNotEmpty())
                    <div class="flex-1 flex items-center justify-center w-full mb-4">
                        <img :src="variantImage || images[activeImage]" alt="{{ $product->name }}" loading="lazy"
                            class="max-w-full max-h-[300px] md:max-h-[350px] object-contain hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="flex gap-2 overflow-x-auto pb-2 max-w-full"
                        x-show="!variantImage && images.length > 1">
                        <template x-for="(img, index) in images" :key="index">
                            <button type="button" @click="activeImage = index"
                                class="w-14 h-14 md:w-16 md:h-16 shrink-0 rounded-lg border-2 overflow-hidden transition p-0.5"
                                :class="activeImage === index ? 'border-amber-500' : 'border-gray-200 hover:border-gray-300'">
                                <img :src="img" :alt="'Gambar ' + (index + 1)" class="w-full h-full object-contain rounded">
                            </button>
                        </template>
                    </div>
                @else
                    <svg class="h-28 w-28 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                @endif
            </div>

            {{-- Info --}}
            <div class="p-6 md:p-8 lg:p-10 flex flex-col">
                @if($product->featured)
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-gradient-to-r from-amber-100 to-orange-100 text-amber-700 px-3 py-1 rounded-full w-fit mb-3">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Produk Unggulan
                    </span>
                @endif

                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">{{ $product->name }}</h1>

                @if($product->sku)
                    <p class="text-sm text-gray-400 mb-1">SKU: {{ $product->sku }}</p>
                @endif
                @if($product->brand)
                    <p class="text-sm text-gray-400 mb-3">Merek: <a href="{{ route('products.index', ['brand' => $product->brand->slug]) }}" class="text-amber-600 hover:text-amber-700 font-medium">{{ $product->brand->name }}</a></p>
                @endif

                {{-- Rating --}}
                <div class="flex items-center gap-2 mb-4">
                    <div class="flex">
                        @for($i = 0; $i < 5; $i++)
                            <svg class="w-4 h-4 {{ $i < ($product->approved_reviews_avg_rating ?? 0) ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        @endfor
                    </div>
                    <span class="text-sm text-gray-400">{{ number_format($product->approved_reviews_avg_rating ?? 0, 1) }} ({{ $product->approved_reviews_count ?? 0 }} ulasan)</span>
                </div>

                {{-- Price --}}
                <div class="mb-5">
                    <span class="text-3xl lg:text-4xl font-extrabold text-amber-600" x-text="'Rp' + displayPrice.toLocaleString('id-ID')">Rp{{ number_format($effectiveBasePrice, 0, ',', '.') }}</span>
                    @if($flashPrice)
                        <span class="text-lg text-gray-400 line-through ml-3">Rp{{ number_format($product->price, 0, ',', '.') }}</span>
                        <span class="ml-2 text-sm font-bold text-red-500 bg-red-50 px-2.5 py-0.5 rounded-lg">-{{ round((1 - $flashPrice / $product->price) * 100) }}%</span>
                    @elseif($product->compare_price && $product->compare_price > $product->price)
                        <span class="text-lg text-gray-400 line-through ml-3">Rp{{ number_format($product->compare_price, 0, ',', '.') }}</span>
                        <span class="ml-2 text-sm font-bold text-red-500 bg-red-50 px-2.5 py-0.5 rounded-lg">-{{ round((1 - $product->price / $product->compare_price) * 100) }}%</span>
                    @endif
                    @if($flashProductData)
                        <span class="ml-2 inline-flex items-center gap-1 text-[11px] font-bold bg-gradient-to-r from-red-600 to-pink-500 text-white px-2.5 py-1 rounded-lg">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                            Flash Sale
                        </span>
                    @endif
                </div>

                {{-- Stock & Weight --}}
                <div class="flex flex-wrap gap-4 mb-6">
                    <div class="flex items-center gap-2 text-sm">
                        <template x-if="displayStock > 0">
                            <span class="flex items-center gap-1.5"><span class="inline-block w-2 h-2 bg-emerald-500 rounded-full"></span> <span class="text-emerald-600 font-medium" x-text="'Stok: ' + displayStock"></span></span>
                        </template>
                        <template x-if="displayStock === 0">
                            <span class="flex items-center gap-1.5"><span class="inline-block w-2 h-2 bg-red-500 rounded-full"></span> <span class="text-red-600 font-medium">Stok Habis</span></span>
                        </template>
                    </div>
                    <div class="flex items-center gap-1.5 text-sm text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                        <span x-text="displayWeight + ' kg'">{{ $product->weight }} kg</span>
                    </div>
                </div>

                {{-- Share Button --}}
                <div class="flex gap-2 mb-4" x-data="{ showShare: false }">
                    <button type="button" @click="showShare = !showShare"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-amber-600 border border-gray-200 hover:border-amber-300 px-3 py-1.5 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Bagikan
                    </button>
                    <template x-if="showShare" x-cloak x-transition>
                        <div class="flex gap-1.5">
                            <a :href="'https://wa.me/?text=' + encodeURIComponent({{ Js::from($product->name) }} + ' - Rp{{ number_format($effectiveBasePrice, 0, ',', '.') }}\n' + window.location.href)"
                                target="_blank" rel="noopener"
                                class="inline-flex items-center gap-1 text-xs font-medium text-white bg-green-500 hover:bg-green-600 px-3 py-1.5 rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </a>
                            <button type="button"
                                @click="navigator.clipboard.writeText(window.location.href); $el.textContent = 'Disalin!'; setTimeout(() => $el.textContent = 'Salin Link', 1500)"
                                class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Salin Link
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Variant Selector --}}
                <template x-for="(attrs, type) in attributes" :key="type">
                    <div class="border-t border-gray-100 pt-4 mt-4" x-show="hasVariants">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider" x-text="typeLabels[type] || type"></span>
                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                            <template x-for="attr in attrs" :key="attr.id">
                                <button type="button" @click="selectAttribute(type, attr.id)"
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition"
                                    :class="selectedAttributes[type] === attr.id
                                        ? 'bg-amber-100 text-amber-800 border-amber-300'
                                        : 'bg-gray-50 text-gray-700 border-gray-200 hover:border-gray-300'"
                                    x-text="attr.label"></button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Product Attributes (non-variant) --}}
                @if(!$hasVariants && $product->attributes->count() > 0)
                    <div class="border-t border-gray-100 pt-4 mt-4 space-y-3">
                        @foreach($attrGroups as $type => $attrs)
                            <div>
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    {{ ['color' => 'Warna', 'size' => 'Ukuran', 'material' => 'Bahan'][$type] ?? $type }}
                                </span>
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach($attrs as $attr)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200">
                                            {{ $attr->label ?? $attr->value }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Description --}}
                <div class="prose prose-sm text-gray-600 mb-6 max-w-none border-t border-gray-100 pt-5" x-data="{ expanded: false }">
                    <h4 class="text-sm font-semibold text-gray-900 mb-2">Deskripsi Produk</h4>
                    <div class="relative" :class="{ 'max-h-32 overflow-hidden': !expanded }">
                        {!! nl2br(e($product->description)) !!}
                        <div x-show="!expanded" class="absolute bottom-0 left-0 right-0 h-10 bg-gradient-to-t from-white to-transparent"></div>
                    </div>
                    <button type="button" @click="expanded = !expanded"
                        class="mt-2 text-xs font-semibold text-amber-600 hover:text-amber-700 transition flex items-center gap-1">
                        <span x-text="expanded ? 'Tutup' : 'Lihat Selengkapnya'"></span>
                        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                </div>

                {{-- Add to Cart --}}
                <div x-show="displayStock > 0" class="mt-auto">
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        @csrf
                        <input type="hidden" name="variant_id" x-model="selectedVariantId">
                        <div class="flex items-center border-2 border-gray-200 rounded-xl overflow-hidden self-center">
                            <button type="button" @click="qty = Math.max(1, qty - 1)"
                                class="px-4 py-2.5 text-gray-500 hover:bg-gray-100 transition text-lg leading-none font-medium">−</button>
                            <input type="number" name="quantity" x-model="qty" :min="1" :max="displayStock"
                                class="w-14 text-center border-x-2 border-gray-200 py-2.5 text-sm font-semibold focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            <button type="button" @click="qty = Math.min(displayStock, qty + 1)"
                                class="px-4 py-2.5 text-gray-500 hover:bg-gray-100 transition text-lg leading-none font-medium">+</button>
                        </div>
                        <button type="submit" :disabled="hasVariants && !selectedVariantId"
                            class="w-full sm:flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            <span x-text="hasVariants && !selectedVariantId ? 'Pilih Varian' : '+ Keranjang'">+ Keranjang</span>
                        </button>
                    </form>
                    {{-- Buy Now Button (Shopee-style) --}}
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="variant_id" x-model="selectedVariantId">
                        <input type="hidden" name="quantity" x-model="qty">
                        <input type="hidden" name="buy_now" value="1">
                        <button type="submit" :disabled="hasVariants && !selectedVariantId"
                            class="w-full bg-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-amber-700 shadow-sm hover:shadow transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Beli Sekarang
                        </button>
                    </form>
                </div>
                <div x-show="displayStock === 0" class="mt-auto">
                    <button disabled class="w-full bg-gray-200 text-gray-400 px-6 py-3 rounded-xl font-semibold cursor-not-allowed flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Stok Habis
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reviews Section --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mt-8 lg:mt-12">
        <div class="p-6 lg:p-8">
            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Ulasan ({{ $product->approved_reviews_count ?? 0 }})
            </h2>

            @if($reviews->isEmpty())
                <p class="text-gray-400 text-sm">Belum ada ulasan untuk produk ini.</p>
            @else
                <div class="space-y-5">
                    @foreach($reviews as $review)
                        <div class="border-b border-gray-100 pb-5 last:border-b-0 last:pb-0">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-9 h-9 bg-gradient-to-br from-amber-100 to-orange-100 rounded-full flex items-center justify-center text-sm font-bold text-amber-700">
                                    {{ strtoupper(substr($review->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $review->user->name }}</p>
                                    <div class="flex items-center gap-1">
                                        @for($i = 0; $i < 5; $i++)
                                            <svg class="w-3.5 h-3.5 {{ $i < $review->rating ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                        <span class="text-[10px] text-gray-400 ml-1">{{ $review->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="text-sm text-gray-600 ml-12">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Review Form --}}
            <div id="review-form"></div>
            @auth
                @if($userReview)
                    <div class="mt-6 pt-6 border-t border-gray-100">
                        <p class="text-sm text-gray-500 mb-3">Kamu sudah memberikan ulasan. Sunting ulasanmu:</p>
                    </div>
                @endif
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900 mb-4">{{ $userReview ? 'Edit Ulasan' : 'Tulis Ulasan' }}</h3>
                    <form action="{{ route('products.review', $product) }}" method="POST" class="max-w-lg space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Rating</label>
                            <div class="flex gap-2" x-data="{ rating: {{ $userReview?->rating ?? 0 }} }">
                                <template x-for="star in 5" :key="star">
                                    <button type="button" @click="rating = star"
                                        class="p-0.5 transition hover:scale-110"
                                        :class="rating >= star ? 'text-amber-400' : 'text-gray-200'">
                                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    </button>
                                </template>
                                <input type="hidden" name="rating" x-model="rating" :value="rating">
                            </div>
                            @error('rating')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Komentar <span class="text-gray-400 font-normal">(opsional)</span></label>
                            <textarea name="comment" rows="3" placeholder="Bagikan pengalamanmu dengan produk ini..."
                                class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">{{ old('comment', $userReview?->comment) }}</textarea>
                        </div>
                        <button type="submit" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-2.5 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition text-sm">
                            {{ $userReview ? 'Perbarui Ulasan' : 'Kirim Ulasan' }}
                        </button>
                    </form>
                </div>
            @else
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <p class="text-sm text-gray-500">
                        <a href="{{ route('login') }}" class="text-amber-600 font-semibold hover:text-amber-700">Login</a> untuk memberikan ulasan.
                    </p>
                </div>
            @endauth
        </div>
    </div>

    {{-- Related Products --}}
    @if($relatedProducts->isNotEmpty())
        <section class="mt-10 lg:mt-14">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Produk Terkait</h2>
                <a href="{{ route('products.index', ['category' => $product->category?->slug]) }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition">Lihat Semua &rarr;</a>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
                @foreach($relatedProducts as $related)
                    <x-product-card :product="$related" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Recently Viewed --}}
    @if($recentProducts->isNotEmpty())
        <section class="mt-10 lg:mt-14">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900">Baru Dilihat</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
                @foreach($recentProducts as $recent)
                    <x-product-card :product="$recent" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Sticky Mobile Add to Cart (Shopee-style) --}}
    <div x-data="productVariant({ variants: {{ Js::from($product->variants->where('is_active', true)->values()->map(fn($v) => ['id' => $v->id, 'price' => (float) $v->price, 'stock' => $v->stock, 'attribute_ids' => $v->attributeValues->pluck('id')->sort()->values()->all(), 'image' => $v->image ? (str_starts_with($v->image, 'http') ? $v->image : Storage::url($v->image)) : null])) }}, attributes: {{ Js::from($attrGroups) }}, requiredTypes: {{ Js::from($requiredAttrTypes) }}, basePrice: {{ (float) $effectiveBasePrice }}, baseStock: {{ $product->stock }}, baseWeight: {{ (float) ($product->weight ?? 200) }}, baseImage: '{{ $product->main_image }}', images: {{ $product->productImages->pluck('url')->toJson() }}, hasVariants: {{ $hasVariants ? 'true' : 'false' }}, flashPrice: {{ $flashPrice ? $flashPrice : 'null' }}, flashStock: {{ $flashProductData ? ($flashProductData['max_qty'] ?? 0) - ($flashProductData['sold_count'] ?? 0) : 'null' }}, flashProductData: {{ $flashProductData ? Js::from($flashProductData) : 'null' }}, selectedVariantId: null, qty: 1, selectedAttributes: {} })"
        class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg p-3 z-40 lg:hidden"
        x-show="displayStock > 0">
        <div class="max-w-7xl mx-auto flex gap-3">
            <a href="{{ route('cart.index') }}" class="flex flex-col items-center justify-center text-gray-500 hover:text-amber-600 transition px-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <span class="text-[10px] mt-0.5">Keranjang</span>
            </a>
            <form action="{{ route('cart.add', $product) }}" method="POST" class="flex-1 flex gap-2">
                @csrf
                <input type="hidden" name="variant_id" x-model="selectedVariantId">
                <input type="hidden" name="quantity" x-model="qty">
                <button type="submit" :disabled="hasVariants && !selectedVariantId"
                    class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-xl font-semibold text-sm hover:from-amber-600 hover:to-orange-600 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-text="hasVariants && !selectedVariantId ? 'Pilih Varian' : '+ Keranjang'">+ Keranjang</span>
                </button>
            </form>
            <form action="{{ route('cart.add', $product) }}" method="POST" class="flex-1">
                @csrf
                <input type="hidden" name="variant_id" x-model="selectedVariantId">
                <input type="hidden" name="quantity" x-model="qty">
                <input type="hidden" name="buy_now" value="1">
                <button type="submit" :disabled="hasVariants && !selectedVariantId"
                    class="w-full bg-amber-600 text-white rounded-xl font-semibold text-sm hover:bg-amber-700 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Beli Sekarang
                </button>
            </form>
        </div>
    </div>
    {{-- Spacer to prevent content being hidden by sticky bar on mobile --}}
    <div class="h-20 lg:hidden"></div>
@endsection

@push('scripts')
<script>
    function productVariant(config) {
        return {
            variants: config.variants,
            attributes: config.attributes,
            requiredTypes: config.requiredTypes,
            hasVariants: config.hasVariants,
            basePrice: config.basePrice,
            baseStock: config.baseStock,
            baseWeight: config.baseWeight,
            baseImage: config.baseImage,
            images: config.images,
            selectedAttributes: {},
            selectedVariantId: null,
            activeImage: 0,
            qty: 1,
            typeLabels: { color: 'Warna', size: 'Ukuran', material: 'Bahan' },

            get selectedVariant() {
                if (!this.hasVariants) return null;
                if (!this.requiredTypes.every(t => this.selectedAttributes[t])) return null;
                const ids = Object.values(this.selectedAttributes).sort((a, b) => a - b);
                return this.variants.find(v =>
                    ids.length === v.attribute_ids.length &&
                    ids.every(id => v.attribute_ids.includes(id))
                ) || null;
            },

            get displayPrice() {
                const v = this.selectedVariant;
                return v ? v.price : this.basePrice;
            },

            get displayStock() {
                const v = this.selectedVariant;
                if (this.hasVariants) return v ? v.stock : 0;
                return this.baseStock;
            },

            get displayWeight() {
                const v = this.selectedVariant;
                return v ? v.weight : this.baseWeight;
            },

            get variantImage() {
                const v = this.selectedVariant;
                return v && v.image ? v.image : null;
            },

            selectAttribute(type, attrId) {
                if (this.selectedAttributes[type] === attrId) {
                    delete this.selectedAttributes[type];
                } else {
                    this.selectedAttributes[type] = attrId;
                }
                this.selectedVariantId = this.selectedVariant ? this.selectedVariant.id : null;
                this.qty = 1;
            }
        }
    }
</script>
@endpush
