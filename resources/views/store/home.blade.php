@extends('layouts.store')

@section('title', 'Home')

@section('content')
    {{-- Hero Slider --}}
    @if($sliders->isNotEmpty())
        <div x-data="{
            current: 0,
            total: {{ $sliders->count() }},
            interval: null,
            next() { this.current = (this.current + 1) % this.total; },
            prev() { this.current = (this.current - 1 + this.total) % this.total; },
            start() { this.interval = setInterval(() => this.next(), 5000); },
            destroy() { if (this.interval) clearInterval(this.interval); }
        }" x-init="start()" class="relative overflow-hidden rounded-2xl md:rounded-3xl mb-8 lg:mb-12">
            @foreach($sliders as $i => $slider)
                @php $sliderImgUrl = $slider->image ? (str_starts_with($slider->image, 'http') ? $slider->image : Storage::url($slider->image)) : ''; @endphp
                <div x-show="current === {{ $i }}" x-cloak class="relative w-full" style="min-height: 320px;">
                    @if($slider->image)
                        {{-- blurred background fill --}}
                        <div class="absolute inset-0 bg-cover bg-center blur-sm scale-110" style="background-image: url('{{ $sliderImgUrl }}')"></div>
                        <div class="absolute inset-0 bg-gray-900/70"></div>
                        {{-- actual image, contained --}}
                        <img src="{{ $sliderImgUrl }}" alt="{{ $slider->title ?? 'Slider' }}" class="absolute inset-0 w-full h-full object-contain">
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-500 via-orange-500 to-orange-600"></div>
                    @endif
                    {{-- caption bar at bottom --}}
                    <div class="absolute bottom-0 left-0 right-0 z-10 bg-gradient-to-t from-black/80 via-black/50 to-transparent pt-14 pb-5 md:pb-7 px-6 md:px-10">
                        @if($slider->title)
                            <h2 class="text-white font-extrabold text-xl md:text-3xl lg:text-4xl leading-tight mb-1.5 drop-shadow-lg">{{ $slider->title }}</h2>
                        @endif
                        @if($slider->description)
                            <p class="text-white/80 text-xs md:text-sm mb-3 max-w-xl drop-shadow">{{ $slider->description }}</p>
                        @endif
                        @if($slider->link && $slider->link_label)
                            <a href="{{ $slider->link }}" class="inline-flex items-center gap-1.5 bg-white text-amber-700 font-semibold px-5 py-2 rounded-xl hover:bg-amber-50 transition-all text-xs md:text-sm shadow-lg">
                                {{ $slider->link_label }}
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
            {{-- Navigation dots --}}
            @if($sliders->count() > 1)
                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 flex gap-2">
                    @foreach($sliders as $i => $slider)
                        <button @click="current = {{ $i }}" class="w-2.5 h-2.5 rounded-full transition-all"
                            :class="current === {{ $i }} ? 'bg-white scale-110' : 'bg-white/50 hover:bg-white/70'"></button>
                    @endforeach
                </div>
            @endif
            {{-- Prev/Next arrows --}}
            @if($sliders->count() > 1)
                <button @click="prev()" class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/20 hover:bg-white/40 backdrop-blur-sm rounded-full flex items-center justify-center text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button @click="next()" class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 bg-white/20 hover:bg-white/40 backdrop-blur-sm rounded-full flex items-center justify-center text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </button>
            @endif
        </div>
    @else
        {{-- Static fallback hero --}}
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
            <div class="relative z-10 mt-8 pt-6 border-t border-white/20 flex flex-wrap gap-5 text-white/70 text-xs">
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg> Garansi 30 Hari</span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Pembayaran Aman</span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/><path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3z"/></svg> Pengiriman Cepat</span>
            </div>
        </div>
    @endif

    {{-- Flash Sale --}}
    @if($activeFlashSale && $activeFlashSale->products->isNotEmpty())
        <section class="mb-8 lg:mb-12">
            @php $fsImgUrl = $activeFlashSale->banner_image ? (str_starts_with($activeFlashSale->banner_image, 'http') ? $activeFlashSale->banner_image : Storage::url($activeFlashSale->banner_image)) : ''; @endphp
            @if($activeFlashSale->banner_image)
                <div class="relative w-full h-40 md:h-52 rounded-2xl overflow-hidden mb-4 bg-gray-900">
                    <div class="absolute inset-0 bg-cover bg-center blur-sm scale-110" style="background-image: url('{{ $fsImgUrl }}')"></div>
                    <div class="absolute inset-0 bg-gray-900/60"></div>
                    <img src="{{ $fsImgUrl }}" alt="{{ $activeFlashSale->name }}" class="absolute inset-0 w-full h-full object-contain">
                </div>
            @endif
            <div class="rounded-2xl p-6 lg:p-8 text-white shadow-lg {{ $activeFlashSale->banner_image ? 'bg-gradient-to-r from-red-700 via-red-600 to-pink-600' : 'bg-gradient-to-r from-red-600 via-red-500 to-pink-500' }}">
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl lg:text-2xl font-bold">{{ $activeFlashSale->name }}</h2>
                            @if($activeFlashSale->end_time)
                                <p class="text-white/70 text-sm" x-data="{
                                    endTime: '{{ $activeFlashSale->end_time instanceof \Carbon\Carbon ? $activeFlashSale->end_time->toIso8601String() : $activeFlashSale->end_time }}',
                                    timeLeft: '',
                                    interval: null,
                                    timer() {
                                        const end = new Date(this.endTime).getTime();
                                        this.interval = setInterval(() => {
                                            const now = new Date().getTime();
                                            const diff = end - now;
                                            if (diff <= 0) { this.timeLeft = 'Berakhir'; clearInterval(this.interval); return; }
                                            const h = Math.floor(diff / (1000 * 60 * 60));
                                            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                            const s = Math.floor((diff % (1000 * 60)) / 1000);
                                            this.timeLeft = h.toString().padStart(2,'0') + ':' + m.toString().padStart(2,'0') + ':' + s.toString().padStart(2,'0');
                                        }, 1000);
                                    },
                                    destroy() { if (this.interval) clearInterval(this.interval); }
                                }" x-init="timer()">
                                    <span x-text="timeLeft"></span>
                                </p>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('products.index', ['flash_sale' => $activeFlashSale->id]) }}" class="text-sm font-semibold bg-white/20 hover:bg-white/30 px-4 py-2 rounded-xl transition shrink-0">
                        Lihat Semua
                    </a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
                    @foreach($activeFlashSale->products->take(4) as $product)
                        <x-product-card :product="$product" :flashSaleMap="$flashSaleMap" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

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
                    <x-product-card :product="$product" :flashSaleMap="$flashSaleMap" />
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
                <x-product-card :product="$product" :flashSaleMap="$flashSaleMap" />
            @endforeach
        </div>
    </section>
@endsection
