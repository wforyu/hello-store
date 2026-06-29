<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) - Hello Store</title>
    @stack('meta')
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

    {{-- Announcement Bar --}}
    <div class="bg-gradient-to-r from-amber-600 to-orange-500 text-white text-center text-xs sm:text-sm py-2 px-4 font-medium overflow-hidden">
        @if($announcements->isNotEmpty())
            <div class="flex gap-8 animate-marquee whitespace-nowrap">
                <span>{{ $announcements->pluck('title')->implode(' &bull; ') }}</span>
                <span>{{ $announcements->pluck('title')->implode(' &bull; ') }}</span>
            </div>
        @else
            Free Ongkir untuk pembelian minimal Rp150.000 &bull; Promo Spesial Akhir Bulan!
        @endif
    </div>

    {{-- Navbar --}}
    <nav class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 lg:h-20">

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2.5 group shrink-0">
                    <div class="w-9 h-9 bg-gradient-to-br from-amber-500 to-orange-600 rounded-lg flex items-center justify-center shadow-md group-hover:shadow-lg transition-shadow">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div class="leading-tight">
                        <span class="text-lg font-extrabold text-gray-900 tracking-tight block -mb-0.5">Hello</span>
                        <span class="text-sm font-bold text-amber-600 tracking-wide block">Store</span>
                    </div>
                </a>

                {{-- Search with Suggestions --}}
                <div class="hidden sm:block flex-1 max-w-lg mx-6 lg:mx-10"
                    x-data="searchSuggestions()"
                    @click.away="show = false"
                    @keydown.escape="show = false">
                    <form action="{{ route('products.index') }}" method="GET" class="relative" @submit="show = false">
                        <input type="text" name="search" x-model="query"
                            @input.debounce.300ms="fetchSuggestions"
                            @focus="if (results.length) show = true"
                            placeholder="Cari produk..."
                            autocomplete="off"
                            class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent focus:bg-white text-sm transition">
                        <svg class="absolute left-3.5 top-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>

                        {{-- Suggestions Dropdown --}}
                        <div x-show="show && results.length"
                            x-cloak
                            class="absolute top-full left-0 right-0 mt-2 bg-white rounded-xl border border-gray-200 shadow-lg z-50 overflow-hidden">
                            <template x-for="(p, i) in results" :key="p.id">
                                <a :href="'{{ url('product') }}/' + p.slug"
                                    class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition"
                                    :class="{ 'border-t border-gray-100': i > 0 }">
                                    <img :src="p.image" :alt="p.name"
                                        class="w-10 h-10 rounded-lg object-contain bg-gray-50 shrink-0">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900 truncate" x-text="p.name"></p>
                                        <p class="text-xs font-semibold text-amber-600" x-text="p.price_formatted"></p>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </form>
                </div>

                {{-- Right Actions --}}
                <div class="flex items-center gap-3 lg:gap-5">

                    {{-- Mobile Search Trigger --}}
                    <button type="button" onclick="this.closest('nav').querySelector('.mobile-search').classList.toggle('hidden')" class="sm:hidden text-gray-500 hover:text-amber-600 transition p-1">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </button>

                    {{-- Cart (hidden for cashier) --}}
                    @if(!auth()->check() || auth()->user()->role !== 'cashier')
                    <a href="{{ route('cart.index') }}" class="relative text-gray-500 hover:text-amber-600 transition p-1">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        @php $cartCount = collect(session('cart', []))->sum('quantity'); @endphp
                        @if($cartCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-bold rounded-full h-5 w-5 flex items-center justify-center shadow-sm">
                                {{ $cartCount > 99 ? '99+' : $cartCount }}
                            </span>
                        @endif
                    </a>
                    @endif

                    {{-- User --}}
                    @auth
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button @click="open = !open" class="flex items-center gap-2 text-gray-500 hover:text-amber-600 transition p-1">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="hidden lg:block text-sm font-medium text-gray-700">{{ auth()->user()->name }}</span>
                            </button>
                            <div x-cloak x-show="open" @click="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 z-50 py-1">
                                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'cashier')
                                    <a href="{{ route('pos.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                        POS Kasir
                                    </a>
                                @endif
                                @if(auth()->user()->role !== 'cashier')
                                    <a href="{{ route('account.dashboard') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-amber-50 hover:text-amber-700 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                        Akun Saya
                                    </a>
                                @endif
                                <hr class="my-1 border-gray-100">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-amber-600 transition">Login</a>
                        <a href="{{ route('register') }}" class="text-sm font-semibold bg-gradient-to-r from-amber-500 to-orange-500 text-white px-5 py-2.5 rounded-xl hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">Daftar</a>
                    @endauth

                </div>
            </div>

            {{-- Mobile Search --}}
            <div class="mobile-search hidden sm:hidden pb-3">
                <form action="{{ route('products.index') }}" method="GET" class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Cari produk..."
                        class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white text-sm transition">
                    <svg class="absolute left-3.5 top-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </form>
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
        @if(session('success'))
            <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ session('error') }}
            </div>
        @endif
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-white border-t border-gray-200 mt-12 lg:mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-12">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-8">
                <div class="col-span-2 md:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5 mb-4">
                        <div class="w-8 h-8 bg-gradient-to-br from-amber-500 to-orange-600 rounded-lg flex items-center justify-center shadow-sm">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <span class="text-lg font-extrabold text-gray-900 tracking-tight">Hello Store</span>
                    </a>
                    <p class="text-sm text-gray-500 leading-relaxed mb-3">{{ $settings['store_address'] }}</p>
                    <div class="space-y-1.5 text-sm">
                        @if($settings['phone'])
                            <a href="tel:{{ $settings['phone'] }}" class="flex items-center gap-2 text-gray-500 hover:text-amber-600 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                {{ $settings['phone'] }}
                            </a>
                        @endif
                        @if($settings['whatsapp'])
                            <a href="https://wa.me/{{ $settings['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 text-gray-500 hover:text-amber-600 transition">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                {{ $settings['whatsapp'] }}
                            </a>
                        @endif
                        @if($settings['email'])
                            <a href="mailto:{{ $settings['email'] }}" class="flex items-center gap-2 text-gray-500 hover:text-amber-600 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ $settings['email'] }}
                            </a>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        @if($settings['instagram'])
                            <a href="{{ $settings['instagram'] }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 bg-gray-100 hover:bg-amber-100 rounded-lg flex items-center justify-center text-gray-500 hover:text-amber-600 transition" title="Instagram">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            </a>
                        @endif
                        @if($settings['facebook'])
                            <a href="{{ $settings['facebook'] }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 bg-gray-100 hover:bg-amber-100 rounded-lg flex items-center justify-center text-gray-500 hover:text-amber-600 transition" title="Facebook">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        @endif
                        @if($settings['tiktok'])
                            <a href="{{ $settings['tiktok'] }}" target="_blank" rel="noopener noreferrer" class="w-8 h-8 bg-gray-100 hover:bg-amber-100 rounded-lg flex items-center justify-center text-gray-500 hover:text-amber-600 transition" title="TikTok">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 text-sm mb-4">Belanja</h4>
                    <ul class="space-y-2.5 text-sm text-gray-500">
                        <li><a href="{{ route('products.index') }}" class="hover:text-amber-600 transition">Semua Produk</a></li>
                        <li><a href="{{ route('products.index', ['category' => 'elektronik']) }}" class="hover:text-amber-600 transition">Elektronik</a></li>
                        <li><a href="{{ route('products.index', ['category' => 'fashion-pria']) }}" class="hover:text-amber-600 transition">Fashion Pria</a></li>
                        <li><a href="{{ route('products.index', ['category' => 'fashion-wanita']) }}" class="hover:text-amber-600 transition">Fashion Wanita</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 text-sm mb-4">Bantuan</h4>
                    <ul class="space-y-2.5 text-sm text-gray-500">
                        <li><a href="{{ route('orders.index') }}" class="hover:text-amber-600 transition">Pesanan Saya</a></li>
                        <li><a href="{{ route('cart.index') }}" class="hover:text-amber-600 transition">Keranjang</a></li>
                        <li><a href="{{ route('addresses.index') }}" class="hover:text-amber-600 transition">Alamat</a></li>
                        @if($settings['whatsapp'])
                            <li><a href="https://wa.me/{{ $settings['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="hover:text-amber-600 transition">Kritik &amp; Saran</a></li>
                        @endif
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900 text-sm mb-4">Transfer ke</h4>
                    <div class="space-y-3">
                        @foreach($settings['bank_accounts'] ?? [] as $bank)
                            <div class="flex items-center gap-2">
                                @php $bankName = $bank['bank_name'] ?? ''; @endphp
                                @if($bankName && file_exists(public_path('images/payments/' . strtolower($bankName) . '.svg')))
                                    <img src="{{ asset('images/payments/' . strtolower($bankName) . '.svg') }}" alt="{{ $bankName }}" class="h-7 rounded shadow-sm">
                                @elseif($bankName)
                                    <span class="text-xs font-bold text-gray-700 bg-gray-100 px-2 py-1 rounded">{{ $bankName }}</span>
                                @endif
                                <div class="text-xs">
                                    <p class="font-semibold text-gray-900">{{ $bank['account_number'] ?? '' }}</p>
                                    <p class="text-gray-500">{{ $bank['account_holder'] ?? '' }}</p>
                                </div>
                            </div>
                        @endforeach
                        <div class="flex flex-wrap gap-1.5 mt-2 pt-2 border-t border-gray-100">
                            <img src="{{ asset('images/payments/cod.svg') }}" alt="COD" class="h-7 rounded shadow-sm" loading="lazy">
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-100 mt-8 pt-6 text-center text-xs text-gray-400">
                &copy; {{ date('Y') }} Hello Store. All rights reserved.
            </div>
        </div>
    </footer>
    {{-- Promo Popup --}}
    @if($popups->isNotEmpty())
        @php $popup = $popups->first(); @endphp
        <div x-data="{ show: !sessionStorage.getItem('popup_closed_{{ $popup->id }}') }"
             x-show="show"
             x-init="if (show) document.body.style.overflow = 'hidden'"
             @keydown.window.escape="show = false; document.body.style.overflow = ''; sessionStorage.setItem('popup_closed_{{ $popup->id }}', '1')"
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
             x-cloak>
            <div class="relative max-w-lg w-full bg-white rounded-2xl shadow-2xl overflow-hidden"
                 @click.outside="show = false; document.body.style.overflow = ''; sessionStorage.setItem('popup_closed_{{ $popup->id }}', '1')">
                @if($popup->image)
                    <img src="{{ Storage::url($popup->image) }}" alt="{{ $popup->title }}" class="w-full h-48 sm:h-56 object-cover">
                @endif
                <div class="p-6">
                    @if($popup->title)
                        <h3 class="text-xl font-bold text-gray-900">{{ $popup->title }}</h3>
                    @endif
                    @if($popup->description)
                        <p class="mt-2 text-sm text-gray-600">{{ $popup->description }}</p>
                    @endif
                    <div class="mt-5 flex items-center gap-3">
                        @if($popup->link && $popup->link_label)
                            <a href="{{ $popup->link }}"
                               class="inline-flex items-center gap-1.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-semibold px-5 py-2.5 rounded-xl hover:from-amber-600 hover:to-orange-600 transition">
                                {{ $popup->link_label }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </a>
                        @endif
                        <button @click="show = false; document.body.style.overflow = ''; sessionStorage.setItem('popup_closed_{{ $popup->id }}', '1')"
                                class="text-sm text-gray-500 hover:text-gray-700 font-medium transition">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @stack('scripts')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('searchSuggestions', () => ({
                query: '{{ request('search') }}',
                results: [],
                show: false,
                async fetchSuggestions() {
                    if (this.query.length < 2) {
                        this.results = [];
                        this.show = false;
                        return;
                    }
                    try {
                        const res = await fetch('{{ route('products.suggestions') }}?q=' + encodeURIComponent(this.query));
                        this.results = await res.json();
                        this.show = this.results.length > 0;
                    } catch {
                        this.results = [];
                        this.show = false;
                    }
                }
            }));
        });
    </script>
</body>
</html>
