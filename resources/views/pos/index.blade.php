@extends('layouts.store')

@section('title', 'POS - Kasir')

@push('styles')
<style>
    .pos-layout { display: flex; height: calc(100vh - 120px); gap: 16px; }
    .pos-products { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
    .pos-cart { width: 430px; display: flex; flex-direction: column; background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,.05); flex-shrink: 0; }
    .pos-cart-header { padding: 14px 16px 10px; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
    .pos-cart-items { flex: 1; overflow-y: auto; padding: 6px 12px; }
    .pos-cart-footer { border-top: 1px solid #e5e7eb; padding: 10px 16px 16px; }
    .pos-product-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 10px; cursor: pointer; transition: .12s; }
    .pos-product-card:hover { border-color: #f59e0b; box-shadow: 0 2px 8px rgba(245,158,11,.15); transform: translateY(-1px); }
    .pos-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .pos-cart-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; }
    .pos-cart-item:last-child { border-bottom: none; }
    .pos-qty-btn { width: 26px; height: 26px; border-radius: 6px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; transition: .12s; user-select: none; }
    .pos-qty-btn:hover { background: #fef3c7; border-color: #f59e0b; }
    .pos-qty-btn:disabled { opacity: .35; cursor: not-allowed; }
    .pos-qty-btn:disabled:hover { background: #fff; border-color: #e5e7eb; }
    .category-pill { padding: 5px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; transition: .12s; border: 1.5px solid #e5e7eb; color: #6b7280; background: #fff; white-space: nowrap; }
    .category-pill:hover { border-color: #f59e0b; color: #d97706; }
    .category-pill.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .quick-amount { padding: 6px 12px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 12px; font-weight: 600; cursor: pointer; transition: .12s; background: #fff; color: #374151; }
    .quick-amount:hover { background: #fef3c7; border-color: #f59e0b; }
    .stock-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 7px; border-radius: 999px; }
    .text-shadow { text-shadow: 0 1px 2px rgba(0,0,0,.15); }
    .payment-pill { padding: 6px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: .12s; border: 2px solid #e5e7eb; background: #fff; color: #6b7280; }
    .payment-pill:hover { border-color: #f59e0b; color: #d97706; }
    .payment-pill.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .order-type-pill { padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; cursor: pointer; transition: .12s; border: 1.5px solid #e5e7eb; background: #fff; color: #6b7280; }
    .order-type-pill:hover { border-color: #f59e0b; color: #d97706; }
    .order-type-pill.active { background: #f59e0b; color: #fff; border-color: #f59e0b; }
    .tab-pill { padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: .12s; color: #6b7280; background: #f3f4f6; }
    .tab-pill:hover { background: #fef3c7; color: #d97706; }
    .tab-pill.active { background: #f59e0b; color: #fff; }
    .hold-badge { position: relative; cursor: pointer; }
    .hold-badge .count { position: absolute; top: -6px; right: -6px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; border-radius: 999px; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 4px; }
    .history-item { padding: 8px 10px; border-radius: 8px; border: 1px solid #f3f4f6; font-size: 12px; cursor: pointer; transition: .12s; }
    .history-item:hover { background: #fef3c7; border-color: #f59e0b; }
    .sku-text { font-size: 10px; color: #9ca3af; font-weight: 500; }
    .item-discount-input { width: 60px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 1px 4px; font-size: 11px; text-align: center; }
    .item-discount-input:focus { outline: none; border-color: #f59e0b; }
    .dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.1); z-index: 50; max-height: 200px; overflow-y: auto; }
    .dropdown-item { padding: 8px 12px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
    .dropdown-item:last-child { border-bottom: none; }
    .dropdown-item:hover { background: #fef3c7; }
</style>
@endpush

@section('content')
<div x-data="posApp()" class="pos-layout" @keydown.window="handleKeydown($event)">
    {{-- Left: Products --}}
    <div class="pos-products">
        {{-- Top bar --}}
        <div class="flex items-center gap-2 mb-3 flex-shrink-0 flex-wrap">
            <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
            <h1 class="text-xl font-bold text-gray-900">POS Kasir</h1>

            {{-- Shift Status --}}
            <div class="flex items-center gap-2 ml-2">
                @if($activeShift)
                    <div class="flex items-center gap-2 text-xs bg-emerald-50 border border-emerald-200 text-emerald-700 px-3 py-1.5 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Shift: {{ $activeShift->opened_at->format('H:i') }}
                    </div>
                    <button type="button" @click="showKasKeluar = true"
                        class="text-xs bg-orange-50 border border-orange-200 text-orange-700 px-3 py-1.5 rounded-full hover:bg-orange-100 transition">
                        Kas Keluar
                    </button>
                    <button type="button" @click="showCloseShift = true"
                        class="text-xs bg-red-50 border border-red-200 text-red-700 px-3 py-1.5 rounded-full hover:bg-red-100 transition">
                        Tutup Shift
                    </button>
                @else
                    <button type="button" @click="showOpenShift = true"
                        class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1.5 rounded-full hover:bg-amber-100 transition font-medium">
                        Buka Shift
                    </button>
                @endif
                <a href="{{ route('pos.shift.history') }}" class="text-xs text-gray-500 hover:text-amber-600 transition ml-1">
                    Riwayat Shift
                </a>
            </div>

            <div class="flex gap-1 ml-2">
                <div class="order-type-pill" :class="{ active: orderType === 'dine_in' }" @click="orderType = 'dine_in'">Dine-in</div>
                <div class="order-type-pill" :class="{ active: orderType === 'takeaway' }" @click="orderType = 'takeaway'">Takeaway</div>
            </div>

            <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 ml-2 cursor-pointer select-none">
                <input type="checkbox" x-model="ppnEnabled" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                PPN <span x-text="ppnRate"></span>%
            </label>

            <input type="text" id="search-product" x-model="search" @input.debounce="searchProducts" placeholder="Cari nama / SKU..." x-ref="searchInput"
                class="ml-auto max-w-[200px] border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">

            {{-- Hold badge --}}
            <div class="hold-badge" @click="showHoldModal = true" x-show="holds.length > 0">
                <svg class="w-6 h-6 text-gray-400 hover:text-amber-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="count" x-text="holds.length"></span>
            </div>

            {{-- History button --}}
            <button @click="showHistory = !showHistory" class="text-gray-400 hover:text-amber-600 transition p-1" title="Riwayat Hari Ini">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </button>
        </div>

        {{-- Barcode Scanner --}}
        <div class="mb-3 flex-shrink-0">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <input type="text" id="barcode-input" x-ref="barcodeInput"
                        placeholder="Scan barcode / masukkan SKU..."
                        @keydown.enter.prevent="scanBarcode"
                        class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none bg-white"
                        autofocus>
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                    </svg>
                </div>
                <button type="button" @click="scanBarcode"
                    class="px-4 py-2 bg-gray-900 text-white rounded-xl font-bold text-sm hover:bg-gray-800 transition whitespace-nowrap cursor-pointer">
                    Cari
                </button>
            </div>
            <div x-show="barcodeMessage" x-cloak>
                <p class="text-xs mt-1.5" :class="barcodeSuccess ? 'text-emerald-600' : 'text-red-500'" x-text="barcodeMessage"></p>
            </div>
            <p class="text-xs text-gray-400 mt-1">Fokus: scan barcode otomatis, atau ketik SKU manual (Ctrl+B)</p>
        </div>

        {{-- Category pills --}}
        <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1 flex-shrink-0" x-show="categories.length > 0">
            <template x-for="cat in categories" :key="cat.id">
                <div class="category-pill" :class="{ active: categoryId === cat.id }" @click="filterCategory(cat.id)" x-text="cat.name"></div>
            </template>
            <div class="category-pill" :class="{ active: !categoryId }" @click="filterCategory(null)">Semua</div>
        </div>

        {{-- Today's History --}}
        <div x-show="showHistory" class="mb-3 p-3 bg-gray-50 rounded-xl border border-gray-200 flex-shrink-0 max-h-48 overflow-y-auto">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-gray-600">RIWAYAT HARI INI</span>
                <button @click="showHistory = false" class="text-gray-400 hover:text-red-500 text-sm">&times;</button>
            </div>
            <div x-show="history.length === 0" class="text-xs text-gray-400 text-center py-4">Belum ada transaksi hari ini</div>
            <template x-for="h in history" :key="h.id">
                <div class="history-item flex items-center justify-between" @click="printOrder(h.id)">
                    <div>
                        <span class="font-semibold text-gray-800" x-text="h.order_number"></span>
                        <span class="text-gray-400 ml-2" x-text="h.time"></span>
                        <span class="text-xs ml-2 text-gray-500" x-text="h.customer_name"></span>
                    </div>
                    <span class="font-bold text-gray-900" x-text="'Rp ' + formatPrice(h.total)"></span>
                </div>
            </template>
        </div>

        {{-- Product Grid --}}
        <div class="pos-product-grid" id="productGrid">
            <template x-for="product in products" :key="product.id">
                <div class="pos-product-card" @click="addToCart(product)">
                    <div class="bg-gray-100 rounded-lg h-24 flex items-center justify-center mb-2 text-gray-400 text-xs overflow-hidden relative">
                        <img x-show="product.image" :src="product.image" :alt="product.name + ' gambar'" class="w-full h-full object-cover rounded-lg">
                        <span x-show="!product.image">Gambar</span>
                        <span x-show="product.stock <= 5" class="stock-badge absolute top-1 right-1 bg-red-500 text-white">Habis</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 truncate leading-tight" x-text="product.name"></p>
                    <p class="sku-text truncate" x-show="product.sku" x-text="product.sku"></p>
                    <p class="text-sm font-bold text-amber-600 mt-0.5" x-text="'Rp ' + formatPrice(product.price)"></p>
                    <p class="text-xs" :class="product.stock <= 5 ? 'text-red-500 font-semibold' : 'text-gray-400'" x-text="'Stok: ' + product.stock"></p>
                </div>
            </template>
            <div x-show="products.length === 0" class="col-span-full text-center py-16 text-gray-400">
                Produk tidak ditemukan
            </div>
        </div>

        {{-- Keyboard shortcuts hint --}}
        <div class="mt-2 text-[10px] text-gray-400 text-center leading-relaxed flex-shrink-0">
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">F2</kbd> Cari</span>
            <span class="mx-1.5">·</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">F4</kbd> Bayar</span>
            <span class="mx-1.5">·</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">F8</kbd> Hold</span>
            <span class="mx-1.5">·</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">Ctrl+B</kbd> Barcode</span>
            <span class="mx-1.5">·</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">Enter</kbd> Checkout</span>
            <span class="mx-1.5">·</span>
            <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-[9px] font-mono">Esc</kbd> Reset</span>
        </div>
    </div>

    {{-- Right: Cart --}}
    <div class="pos-cart">
        {{-- Cart tab header --}}
        <div class="pos-cart-header">
            <div class="flex gap-2">
                <div class="tab-pill" :class="{ active: cartTab === 'cart' }" @click="cartTab = 'cart'">Keranjang</div>
                <div class="tab-pill" :class="{ active: cartTab === 'history' }" @click="loadHistory(); cartTab = 'history'">Riwayat</div>
            </div>
            <span class="text-sm text-gray-400" x-show="cartTab === 'cart'" x-text="cart.length + ' item'"></span>
        </div>

        {{-- Cart Items Tab --}}
        <div class="pos-cart-items" x-show="cartTab === 'cart'">
            <template x-for="(item, idx) in cart" :key="item.product_id">
                <div class="pos-cart-item">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate leading-tight" x-text="item.name"></p>
                        <p class="text-xs text-gray-400">
                            Rp <span x-text="formatPrice(item.price)"></span> × <span x-text="item.quantity"></span>
                        </p>
                        {{-- Per-item discount --}}
                        <div class="flex items-center gap-1 mt-1">
                            <input type="text" class="item-discount-input" x-model="item.discountText" @input="updateItemDiscount(item, idx)" placeholder="Diskon" inputmode="numeric">
                            <button class="text-[10px] font-bold px-1.5 py-0.5 rounded border transition"
                                :class="item.discountType === 'percent' ? 'bg-amber-100 text-amber-700 border-amber-300' : 'bg-gray-100 text-gray-500 border-gray-300'"
                                @click="toggleItemDiscountType(item)" x-text="item.discountType === 'percent' ? '%' : 'Rp'"></button>
                            <span class="text-xs font-semibold text-gray-600">Rp <span x-text="formatPrice(itemTotal(item))"></span></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button class="pos-qty-btn" @click="updateQty(item.product_id, item.quantity - 1)">−</button>
                        <span class="text-sm font-bold w-5 text-center" x-text="item.quantity"></span>
                        <button class="pos-qty-btn" @click="updateQty(item.product_id, item.quantity + 1)" :disabled="item.quantity >= item.stock">+</button>
                    </div>
                    <button @click="removeItem(item.product_id)" class="text-gray-300 hover:text-red-500 transition text-base leading-none ml-1 flex-shrink-0">&times;</button>
                </div>
            </template>

            {{-- Empty state --}}
            <div x-show="cart.length === 0 && !checkoutResult" class="text-center py-16 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-2 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                Belum ada item
            </div>

            {{-- Success result --}}
            <div x-show="checkoutResult" class="text-center py-8">
                <div class="w-16 h-16 mx-auto mb-3 bg-emerald-100 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-lg font-extrabold text-gray-900">Pembayaran Berhasil!</p>
                <p class="text-sm text-gray-500 mt-1" x-text="'No: ' + checkoutResult"></p>
                <p class="text-sm text-emerald-600 font-bold mt-1" x-text="'Kembali: Rp ' + formatPrice(changeAmount)"></p>
                <p x-show="ppnAmount > 0" class="text-xs text-gray-500 mt-0.5" x-text="'PPN ' + ppnRate + '%: Rp ' + formatPrice(ppnAmount)"></p>
                <div class="flex gap-2 mt-4 justify-center">
                    <button @click="printReceipt()" class="px-5 py-2.5 bg-gray-900 text-white text-sm font-semibold rounded-xl hover:bg-gray-800 transition shadow-sm cursor-pointer">
                        🖨 Cetak Nota
                    </button>
                    <button @click="resetPos()" class="px-5 py-2.5 bg-amber-500 text-white text-sm font-semibold rounded-xl hover:bg-amber-600 transition shadow-sm cursor-pointer">
                        + Pesanan Baru
                    </button>
                </div>
            </div>
        </div>

        {{-- History Tab --}}
        <div class="pos-cart-items" x-show="cartTab === 'history'">
            <div x-show="history.length === 0" class="text-center py-16 text-gray-400">Belum ada transaksi</div>
            <template x-for="h in history" :key="h.id">
                <div class="history-item flex items-center justify-between mb-1" @click="printOrder(h.id)">
                    <div class="min-w-0">
                        <span class="font-semibold text-gray-800 text-sm" x-text="h.order_number"></span>
                        <span class="text-gray-400 ml-2 text-xs" x-text="h.time"></span>
                        <p class="text-xs text-gray-500 truncate" x-text="h.customer_name"></p>
                    </div>
                    <span class="font-bold text-gray-900 text-sm flex-shrink-0" x-text="'Rp ' + formatPrice(h.total)"></span>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="pos-cart-footer" x-show="cart.length > 0 && !checkoutResult && cartTab === 'cart'">
            {{-- Customer search --}}
            <div class="relative mb-3">
                <input type="text" x-model="customerName" @input="searchCustomers" @focus="searchCustomers" @blur="setTimeout(() => showCustomerDropdown = false, 200)" @keydown.enter.prevent="focusAmount()"
                    placeholder="Nama pelanggan (ketik untuk cari)..."
                    class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                <div class="dropdown" x-show="showCustomerDropdown && customerResults.length > 0">
                    <template x-for="c in customerResults" :key="c.id">
                        <div class="dropdown-item" @click="selectCustomer(c)">
                            <span class="font-semibold" x-text="c.name"></span>
                            <span class="text-gray-400 ml-2 text-xs" x-text="c.email"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Global discount --}}
            <div class="flex items-center gap-2 mb-2">
                <div class="relative flex-1">
                    <input type="text" x-model="discountText" @input="formatDiscount" :placeholder="'Diskon (' + (discountMode === 'percent' ? '%' : 'Rp') + ')'" inputmode="numeric"
                        class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <div x-show="discountNum > 0" class="text-xs text-gray-400 mt-0.5">
                        <template x-if="discountMode === 'percent'">
                            <span x-text="'potongan: Rp ' + formatPrice(discountNum) + ' (' + discountText.replace(/\D/g,'') + '%)'"></span>
                        </template>
                        <template x-if="discountMode === 'nominal'">
                            <span>potongan: Rp <span x-text="formatPrice(discountNum)"></span></span>
                        </template>
                    </div>
                </div>
                <button @click="toggleDiscountMode" class="text-xs font-bold px-3 py-1.5 rounded-lg border transition"
                    :class="discountMode === 'percent' ? 'bg-amber-100 text-amber-700 border-amber-300' : 'bg-gray-100 text-gray-600 border-gray-300'"
                    x-text="discountMode === 'percent' ? '%' : 'Rp'"></button>
            </div>

            {{-- Payment method --}}
            <div class="flex gap-1.5 mb-3 flex-wrap">
                <template x-for="pm in paymentMethods" :key="pm.id">
                    <div class="payment-pill" :class="{ active: paymentMethod === pm.id }" @click="paymentMethod = pm.id" x-text="pm.label"></div>
                </template>
            </div>

            {{-- Totals --}}
            <div class="space-y-0.5 mb-3">
                <div class="flex justify-between items-baseline">
                    <span class="text-sm text-gray-500">Subtotal</span>
                    <span class="text-sm font-semibold" x-text="'Rp ' + formatPrice(itemSubtotal)"></span>
                </div>
                <div x-show="itemDiscountTotal > 0" class="flex justify-between items-baseline">
                    <span class="text-sm text-red-500">Diskon per Item</span>
                    <span class="text-sm font-semibold text-red-500" x-text="'- Rp ' + formatPrice(itemDiscountTotal)"></span>
                </div>
                <div x-show="discountNum > 0" class="flex justify-between items-baseline">
                    <span class="text-sm text-red-500">Diskon Global</span>
                    <span class="text-sm font-semibold text-red-500" x-text="'- Rp ' + formatPrice(discountNum)"></span>
                </div>
                <div x-show="ppnEnabled" class="flex justify-between items-baseline">
                    <span class="text-sm text-blue-600">PPN <span x-text="ppnRate"></span>%</span>
                    <span class="text-sm font-semibold text-blue-600" x-text="'+ Rp ' + formatPrice(ppnAmount)"></span>
                </div>
                <div class="flex justify-between items-baseline pt-1 border-t border-gray-200">
                    <span class="text-lg font-extrabold">Total</span>
                    <span class="text-lg font-extrabold text-amber-600" x-text="'Rp ' + formatPrice(total)"></span>
                </div>
            </div>

            {{-- Cash amount input --}}
            <div x-show="paymentMethod === 'cash'" class="space-y-2">
                <input type="text" x-model="amountPaidText" @input="onAmountInput" @focus="$el.select()" x-ref="amountInput"
                    inputmode="numeric" placeholder="Jumlah dibayar"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">

                <div class="flex gap-1.5 flex-wrap">
                    <template x-for="amt in quickAmounts" :key="amt">
                        <button class="quick-amount" @click="setAmount(amt)" x-text="'Rp ' + formatPrice(amt)"></button>
                    </template>
                </div>

                <template x-if="amountPaidNum && amountPaidNum >= total">
                    <p class="text-sm text-emerald-600 font-bold" x-text="'Kembali: Rp ' + formatPrice(amountPaidNum - total)"></p>
                </template>
                <template x-if="amountPaidNum && amountPaidNum > 0 && amountPaidNum < total">
                    <p class="text-sm text-red-500 font-bold" x-text="'Kurang: Rp ' + formatPrice(total - amountPaidNum)"></p>
                </template>
            </div>

            {{-- Bayar + Hold buttons --}}
            <div class="flex gap-2 mt-3">
                <button id="btn-checkout" @click="processCheckout" :disabled="!canCheckout || loading"
                    class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-bold py-3 rounded-xl hover:from-amber-600 hover:to-orange-600 transition disabled:opacity-40 disabled:cursor-not-allowed shadow-sm cursor-pointer text-shadow">
                    <span x-show="!loading">Bayar <span x-text="'Rp ' + formatPrice(total)"></span></span>
                    <span x-show="loading">⏳ Memproses...</span>
                </button>
                <button id="btn-hold" @click="holdCurrentOrder" :disabled="loading"
                    class="px-4 py-3 border border-gray-200 text-gray-500 font-bold rounded-xl hover:bg-gray-50 hover:border-amber-500 hover:text-amber-600 transition disabled:opacity-40 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </div>

            <p x-show="errorMsg" class="text-sm text-red-500 text-center font-medium mt-2" x-text="errorMsg"></p>
        </div>
    </div>

    {{-- Kas Keluar Modal --}}
    <div x-cloak x-show="showKasKeluar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @keydown.escape.window="showKasKeluar = false">
        <div @click.outside="showKasKeluar = false" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Kas Keluar</h3>
            <p class="text-sm text-gray-500 mb-5">Catat pengeluaran dari shift aktif.</p>
            <form action="{{ route('pos.shift.expense') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Jumlah (Rp)</label>
                    <input type="number" name="amount" x-model="kasKeluarAmount" min="1" required
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Kategori</label>
                    <select name="category" x-model="kasKeluarCategory"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                        <option value="operasional">Operasional</option>
                        <option value="belanja">Belanja</option>
                        <option value="kebersihan">Kebersihan</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Keterangan</label>
                    <textarea name="description" x-model="kasKeluarDescription" rows="2" required
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="showKasKeluar = false"
                        class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Batal</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-orange-500 text-white rounded-xl text-sm font-bold hover:bg-orange-600 transition shadow-sm">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Open Shift Modal --}}
    <div x-cloak x-show="showOpenShift" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @keydown.escape.window="showOpenShift = false">
        <div @click.outside="showOpenShift = false" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Buka Shift</h3>
            <p class="text-sm text-gray-500 mb-5">Masukkan saldo awal untuk membuka shift.</p>
            <form action="{{ route('pos.shift.open') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Saldo Awal (Rp)</label>
                    <input type="number" name="opening_balance" x-model="openingBalance" min="0" value="0"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="showOpenShift = false"
                        class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Batal</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-emerald-500 text-white rounded-xl text-sm font-bold hover:bg-emerald-600 transition shadow-sm">
                        Buka Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Close Shift Modal --}}
    <div x-cloak x-show="showCloseShift" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @keydown.escape.window="showCloseShift = false">
        <div @click.outside="showCloseShift = false" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Tutup Shift</h3>
            <p class="text-sm text-gray-500 mb-5">Masukkan saldo akhir untuk menutup shift.</p>
            @if($activeShift)
                @if($activeShift->expenses->count() > 0)
                    <div class="mb-4 p-3 bg-orange-50 border border-orange-100 rounded-xl">
                        <p class="text-xs font-medium text-orange-700 mb-1">Total Pengeluaran Shift Ini</p>
                        <p class="text-sm font-bold text-orange-800">Rp {{ number_format($activeShift->expenses->sum('amount'), 0, ',', '.') }}</p>
                        <ul class="mt-1.5 space-y-1">
                            @foreach($activeShift->expenses as $expense)
                                <li class="text-xs text-orange-600 flex justify-between">
                                    <span>{{ $expense->description }}</span>
                                    <span class="font-medium">Rp {{ number_format($expense->amount, 0, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
            <form action="{{ route('pos.shift.close') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Saldo Akhir (Rp)</label>
                    <input type="number" name="closing_balance" x-model="closingBalance" min="0" required
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Catatan</label>
                    <textarea name="notes" x-model="shiftNotes" rows="2"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" @click="showCloseShift = false"
                        class="flex-1 px-4 py-2.5 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Batal</button>
                    <button type="submit"
                        class="flex-1 px-4 py-2.5 bg-red-500 text-white rounded-xl text-sm font-bold hover:bg-red-600 transition shadow-sm">
                        Tutup Shift
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Hold Orders Modal --}}
    <div x-show="showHoldModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" @keydown.window.escape="showHoldModal = false" x-cloak>
        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-2xl p-6" @click.outside="showHoldModal = false">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Pesanan Ditahan</h3>
            <div x-show="holds.length === 0" class="text-center py-8 text-gray-400">Tidak ada pesanan ditahan</div>
            <template x-for="h in holds" :key="h.id">
                <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl mb-2 hover:border-amber-500 transition">
                    <div>
                        <p class="font-semibold text-gray-900 text-sm" x-text="h.name"></p>
                        <p class="text-xs text-gray-400" x-text="h.created_at + ' - ' + (h.customer_name || 'Umum')"></p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="recallOrder(h.id)" class="px-3 py-1.5 bg-amber-500 text-white text-xs font-bold rounded-lg hover:bg-amber-600 transition cursor-pointer">Lanjutkan</button>
                        <button @click="deleteHold(h.id)" class="px-3 py-1.5 border border-gray-200 text-gray-500 text-xs font-bold rounded-lg hover:bg-red-50 hover:text-red-500 transition cursor-pointer">Hapus</button>
                    </div>
                </div>
            </template>
            <button @click="showHoldModal = false" class="mt-3 w-full py-2 text-sm text-gray-500 hover:text-gray-700 font-medium transition cursor-pointer">Tutup</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function posApp() {
        return {
            products: @json($products),
            categories: @json($categories),
            cart: @json($cart->values()),
            customers: @json($customers),
            search: '',
            categoryId: null,
            customerName: '',
            customerResults: [],
            showCustomerDropdown: false,
            selectedCustomerId: null,
            amountPaidText: '',
            discountText: '',
            discountMode: 'percent',
            paymentMethod: 'cash',
            orderType: 'dine_in',
            ppnEnabled: false,
            ppnRate: {{ $ppnRate }},
            checkoutResult: '',
            changeAmount: 0,
            ppnAmount: 0,
            lastOrderId: null,
            loading: false,
            errorMsg: '',
            quickAmounts: [50000, 100000, 200000, 500000, 1000000],
            showHoldModal: false,
            holds: @json(session('pos_holds', [])),
            showHistory: false,
            history: [],
            showKasKeluar: false,
            kasKeluarAmount: 0,
            kasKeluarDescription: '',
            kasKeluarCategory: 'operasional',
            showOpenShift: false,
            showCloseShift: false,
            openingBalance: 0,
            closingBalance: 0,
            shiftNotes: '',
            barcodeMessage: '',
            barcodeSuccess: false,
            cartTab: 'cart',
            paymentMethods: [
                { id: 'cash', label: 'Tunai' },
                { id: 'qris', label: 'QRIS' },
                { id: 'debit', label: 'Debit' },
                { id: 'transfer', label: 'Transfer' },
            ],

            // --- Computed ---
            get itemSubtotal() {
                return this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            },

            get itemDiscountTotal() {
                return this.cart.reduce((sum, item) => sum + this.calcItemDisc(item), 0);
            },

            get afterItemDiscount() {
                return this.itemSubtotal - this.itemDiscountTotal;
            },

            get discountNum() {
                const raw = this.discountText.replace(/\D/g, '');
                const val = raw ? parseInt(raw, 10) : 0;
                if (this.discountMode === 'percent') {
                    return Math.round(this.afterItemDiscount * val / 100);
                }
                return val;
            },

            get ppnAmount() {
                if (!this.ppnEnabled) return 0;
                return Math.round((this.afterItemDiscount - this.discountNum) * this.ppnRate / 100);
            },

            get total() {
                return Math.max(0, this.afterItemDiscount - this.discountNum + this.ppnAmount);
            },

            get amountPaidNum() {
                const raw = this.amountPaidText.replace(/\D/g, '');
                return raw ? parseInt(raw, 10) : 0;
            },

            get canCheckout() {
                if (this.cart.length === 0 || this.loading) return false;
                if (this.paymentMethod === 'cash') return this.amountPaidNum >= this.total;
                return this.total > 0;
            },

            // --- Helpers ---
            formatPrice(num) {
                return num.toLocaleString('id-ID');
            },

            calcItemDisc(item) {
                const total = item.price * item.quantity;
                const disc = parseFloat(item.discount || 0);
                const type = item.discountType || 'nominal';
                if (!disc || disc <= 0) return 0;
                if (type === 'percent') return Math.round(total * Math.min(disc, 100) / 100);
                return Math.min(disc, total);
            },

            itemTotal(item) {
                return (item.price * item.quantity) - this.calcItemDisc(item);
            },

            // --- Input handlers ---
            onAmountInput() {
                var raw = this.amountPaidText.replace(/\D/g, '');
                if (raw === '') { this.amountPaidText = ''; return; }
                var formatted = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                if (this.amountPaidText !== formatted) {
                    this.amountPaidText = formatted;
                }
            },

            formatDiscount() {
                var raw = this.discountText.replace(/\D/g, '');
                if (raw === '') { this.discountText = ''; return; }
                if (this.discountMode === 'nominal') {
                    var formatted = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    if (this.discountText !== formatted) this.discountText = formatted;
                } else {
                    this.discountText = raw;
                }
            },

            setAmount(amt) {
                this.amountPaidText = String(amt);
                this.$refs.amountInput?.focus();
            },

            focusAmount() {
                this.$refs.amountInput?.focus();
            },

            toggleDiscountMode() {
                this.discountMode = this.discountMode === 'percent' ? 'nominal' : 'percent';
                if (this.discountText) this.formatDiscount();
            },

            toggleItemDiscountType(item) {
                item.discountType = item.discountType === 'percent' ? 'nominal' : 'percent';
                if (!item.discountText && item.discount && item.discount > 0) {
                    item.discountText = String(item.discount);
                }
                if (item.discountType === 'nominal' && item.discountText) {
                    var raw = item.discountText.replace(/\D/g, '');
                    var formatted = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    item.discountText = formatted;
                }
                this.syncItemDiscount(item);
            },

            updateItemDiscount(item, idx) {
                var raw = item.discountText.replace(/\D/g, '');
                if (raw === '') { item.discountText = ''; item.discount = 0; this.syncItemDiscount(item); return; }
                if (item.discountType === 'nominal') {
                    var formatted = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    if (item.discountText !== formatted) item.discountText = formatted;
                } else {
                    item.discountText = raw;
                }
                item.discount = parseFloat(raw);
                this.syncItemDiscount(item);
            },

            syncItemDiscount(item) {
                fetch('{{ route("pos.update") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({
                        product_id: item.product_id,
                        quantity: item.quantity,
                        discount: item.discount || 0,
                        discount_type: item.discountType || 'nominal',
                    })
                }).then(r => r.json()).then(data => {
                    if (data.cart) this.cart = data.cart;
                });
            },

            // --- Keyboard ---
            handleKeydown(e) {
                if (e.key === 'F2') {
                    e.preventDefault();
                    document.getElementById('search-product')?.focus();
                    return;
                }
                if (e.key === 'F4') {
                    e.preventDefault();
                    if (this.cart.length > 0) {
                        this.processCheckout();
                    }
                    return;
                }
                if (e.key === 'F8') {
                    e.preventDefault();
                    if (this.cart.length > 0) {
                        document.getElementById('btn-hold')?.click();
                    }
                    return;
                }
                if (e.ctrlKey && e.key === 'b') {
                    e.preventDefault();
                    this.$refs.barcodeInput?.focus();
                    return;
                }
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
                if (e.key === 'Enter' && this.cart.length > 0 && !this.checkoutResult) {
                    if (this.canCheckout) this.processCheckout();
                    else if (this.paymentMethod === 'cash') this.focusAmount();
                }
                if (e.key === 'Escape') {
                    if (this.showHoldModal) this.showHoldModal = false;
                    else this.resetPos();
                }
            },

            // --- Category filter ---
            filterCategory(catId) {
                this.categoryId = catId;
                this.searchProducts();
            },

            // --- Search ---
            searchProducts() {
                let url = '{{ route("pos.search") }}?search=' + encodeURIComponent(this.search);
                if (this.categoryId) url += '&category_id=' + this.categoryId;
                fetch(url).then(r => r.json()).then(data => this.products = data);
            },

            searchCustomers() {
                const val = this.customerName.trim();
                if (!val) {
                    this.customerResults = this.customers;
                } else {
                    fetch('{{ route("pos.customers") }}?search=' + encodeURIComponent(val))
                        .then(r => r.json()).then(data => this.customerResults = data);
                }
                this.showCustomerDropdown = true;
                this.selectedCustomerId = null;
            },

            selectCustomer(c) {
                this.customerName = c.name;
                this.selectedCustomerId = c.id;
                this.showCustomerDropdown = false;
            },

            // --- Cart operations ---
            addToCart(product) {
                this.errorMsg = '';
                fetch('{{ route("pos.add") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({product_id: product.id})
                }).then(r => r.json()).then(data => {
                    if (data.cart) {
                        this.cart = data.cart.map(item => ({
                            ...item,
                            discountText: item.discount ? String(item.discount) : '',
                            discountType: item.discount_type || 'nominal',
                        }));
                    }
                });
            },

            updateQty(productId, qty) {
                if (qty < 1) return;
                this.errorMsg = '';
                fetch('{{ route("pos.update") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({product_id: productId, quantity: qty})
                }).then(r => r.json()).then(data => {
                    if (data.cart) {
                        this.cart = data.cart.map(item => ({
                            ...item,
                            discountText: item.discount ? String(item.discount) : '',
                            discountType: item.discount_type || 'nominal',
                        }));
                    }
                });
            },

            removeItem(productId) {
                this.errorMsg = '';
                fetch('{{ route("pos.remove") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({product_id: productId})
                }).then(r => r.json()).then(data => {
                    if (data.cart) {
                        this.cart = data.cart.map(item => ({
                            ...item,
                            discountText: item.discount ? String(item.discount) : '',
                            discountType: item.discount_type || 'nominal',
                        }));
                    }
                });
            },

            // --- Checkout ---
            processCheckout() {
                if (!this.canCheckout) return;
                this.loading = true;
                this.errorMsg = '';

                fetch('{{ route("pos.checkout") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({
                        customer_name: this.customerName,
                        customer_id: this.selectedCustomerId,
                        amount_paid: this.amountPaidNum,
                        discount: this.discountNum,
                        ppn: this.ppnEnabled ? 1 : 0,
                        payment_method: this.paymentMethod,
                        order_type: this.orderType,
                    })
                }).then(r => {
                    return r.json().then(data => ({status: r.status, data}));
                }).then(({status, data}) => {
                    if (data.success) {
                        this.checkoutResult = data.order_number;
                        this.changeAmount = data.change || 0;
                        this.ppnAmount = data.ppn || 0;
                        this.lastOrderId = data.order_id;
                        this.cart = [];
                        this.discountText = '';
                        this.amountPaidText = '';
                    } else {
                        this.errorMsg = data.error || data.message || 'Terjadi kesalahan';
                    }
                }).catch(() => {
                    this.errorMsg = 'Terjadi kesalahan, coba lagi';
                }).finally(() => {
                    this.loading = false;
                });
            },

            // --- Hold / Recall ---
            holdCurrentOrder() {
                if (this.cart.length === 0) return;
                this.errorMsg = '';
                fetch('{{ route("pos.hold") }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                    body: JSON.stringify({
                        name: this.customerName ? 'Pesanan ' + this.customerName : '',
                        customer_name: this.customerName,
                    })
                }).then(r => r.json()).then(data => {
                    if (data.holds) {
                        this.holds = data.holds;
                        this.cart = [];
                        this.customerName = '';
                        this.discountText = '';
                        this.amountPaidText = '';
                    }
                }).catch(() => {
                    this.errorMsg = 'Gagal menahan pesanan';
                });
            },

            recallOrder(id) {
                fetch('/pos/hold/' + id)
                    .then(r => r.json()).then(data => {
                        if (data.cart) {
                            this.cart = data.cart.map(item => ({
                                ...item,
                                discountText: item.discount ? String(item.discount) : '',
                                discountType: item.discount_type || 'nominal',
                            }));
                            this.customerName = data.customer_name || '';
                            this.holds = data.holds || [];
                            this.showHoldModal = false;
                        }
                    });
            },

            deleteHold(id) {
                fetch('/pos/hold/' + id, {method: 'DELETE', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}})
                    .then(r => r.json()).then(data => {
                        if (data.holds) this.holds = data.holds;
                    });
            },

            // --- History ---
            loadHistory() {
                fetch('{{ route("pos.history") }}')
                    .then(r => r.json()).then(data => {
                        this.history = data;
                    });
            },

            printOrder(orderId) {
                window.open('/pos/print/' + orderId, '_blank');
            },

            // --- Print ---
            printReceipt() {
                if (this.lastOrderId) {
                    window.open('/pos/print/' + this.lastOrderId, '_blank');
                }
            },

            // --- Barcode Scanner ---
            scanBarcode() {
                const input = this.$refs.barcodeInput;
                if (!input || !input.value.trim()) return;

                this.barcodeMessage = '';
                this.barcodeSuccess = false;

                fetch('{{ route("pos.scan") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ barcode: input.value.trim() })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.found) {
                        window.location.reload();
                    } else {
                        this.barcodeSuccess = false;
                        this.barcodeMessage = data.message;
                        input.value = '';
                        input.focus();
                    }
                })
                .catch(() => {
                    this.barcodeMessage = 'Gagal memindai barcode.';
                    input.focus();
                });
            },

            // --- Reset ---
            resetPos() {
                this.cart = [];
                this.customerName = '';
                this.selectedCustomerId = null;
                this.amountPaidText = '';
                this.discountText = '';
                this.paymentMethod = 'cash';
                this.orderType = 'dine_in';
                this.ppnEnabled = false;
                this.checkoutResult = '';
                this.changeAmount = 0;
                this.ppnAmount = 0;
                this.lastOrderId = null;
                this.errorMsg = '';
                this.cartTab = 'cart';
            }
        }
    }
</script>
@endpush
