@extends('layouts.store')

@section('title', 'Checkout')

@section('content')
    <div class="flex items-center gap-3 mb-6">
        <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Checkout</h1>
    </div>

    <form action="{{ route('checkout.place') }}" method="POST" x-data="checkoutForm()" x-init="subtotal = {{ $subtotal }}" @submit="if (submitting) return; submitting = true">
        @csrf

        <div class="grid lg:grid-cols-3 gap-6 lg:gap-8">

            {{-- Left Column --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Address --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Alamat Pengiriman
                        </h2>
                        <a href="{{ route('addresses.create') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700 transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Tambah
                        </a>
                    </div>

                    @if($addresses->isEmpty())
                        <div class="text-center py-10 text-gray-500">
                            <svg class="h-12 w-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p class="mb-4">Belum ada alamat tersimpan</p>
                            <a href="{{ route('addresses.create') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-xl font-medium hover:bg-amber-600 transition text-sm shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Tambah Alamat Baru
                            </a>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($addresses as $address)
                                <label class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition"
                                    :class="selectedAddress == '{{ $address->id }}' ? 'border-amber-500 bg-amber-50/50' : 'border-gray-100 hover:border-amber-200'">
                                    <input type="radio" name="address_id" value="{{ $address->id }}"
                                        {{ old('address_id', $loop->first ? $address->id : '') == $address->id ? 'checked' : '' }}
                                        @change="selectedAddress = '{{ $address->id }}'"
                                        class="mt-0.5 text-amber-500 focus:ring-amber-500">
                                    <div>
                                        <p class="font-semibold text-gray-900">
                                            {{ $address->label ? $address->label . ' - ' : '' }}{{ $address->recipient }}
                                            @if($address->is_default)
                                                <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded ml-1">UTAMA</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500 mt-0.5">{{ $address->phone }}</p>
                                        <p class="text-sm text-gray-400 mt-0.5">{{ $address->street }}, {{ $address->city }}, {{ $address->province }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('address_id')
                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                {{-- Shipping Courier --}}
                @if(!empty($shippingRates))
                    <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-1m4 0l2 1m4 0l2-1m4 0l2 1M4 20h16a1 1 0 001-1v-3a1 1 0 00-1-1H3a1 1 0 00-1 1v3a1 1 0 001 1z"/></svg>
                            Pilih Kurir
                        </h2>
                        <p class="text-xs text-gray-400 mb-4">Total berat: <span class="font-semibold text-gray-600">{{ $totalWeight }} gr</span></p>

                        <div class="space-y-4" x-ref="courierList">
                            @foreach($shippingRates as $courier)
                                <div class="border-2 border-gray-100 rounded-xl overflow-hidden">
                                    <div class="bg-gray-50 px-4 py-3 font-bold text-gray-800 text-sm flex items-center gap-2">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2-1m4 0l2 1m4 0l2-1m4 0l2-1M4 20h16a1 1 0 001-1v-3a1 1 0 00-1-1H3a1 1 0 00-1 1v3a1 1 0 001 1z"/></svg>
                                        {{ $courier['name'] }}
                                    </div>
                                    <div class="divide-y divide-gray-50">
                                        @foreach($courier['rates'] as $rate)
                                            <label class="flex items-center gap-3 px-4 py-3 cursor-pointer transition hover:bg-amber-50/30">
                                                <input type="radio" name="shipping_rate"
                                                    value="{{ $courier['code'] }}|{{ $rate['service'] }}|{{ $rate['cost'] }}"
                                                    @change="selectShipping('{{ $courier['name'] }}', '{{ $courier['code'] }} - {{ $rate['service'] }}', {{ $rate['cost'] }}, '{{ addslashes($rate['description']) }}')"
                                                    class="text-amber-500 focus:ring-amber-500 shrink-0">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-semibold text-gray-900">{{ $rate['service'] }}</p>
                                                    <p class="text-xs text-gray-400 truncate">
                                                        {{ $rate['description'] }}
                                                        @if($rate['etd'] !== '-')
                                                            · Estimasi {{ $rate['etd'] }} hari
                                                        @endif
                                                    </p>
                                                </div>
                                                <span class="text-sm font-bold text-amber-600 shrink-0">Rp{{ number_format($rate['cost'], 0, ',', '.') }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <input type="hidden" name="shipping_courier" x-model="selectedCourier">
                        <input type="hidden" name="shipping_service" x-model="selectedService">
                        <input type="hidden" name="shipping_cost" x-model="selectedCost">

                        <template x-if="selectedCourier">
                            <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span x-text="'Dipilih: ' + selectedCourier + ' — Rp ' + selectedCost.toLocaleString('id-ID')"></span>
                            </div>
                        </template>

                        @error('shipping_courier')
                            <p class="text-sm text-red-500 mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <input type="hidden" name="shipping_courier" value="flat">
                    <input type="hidden" name="shipping_service" value="Reguler">
                    <input type="hidden" name="shipping_cost" value="15000">
                @endif

                {{-- Payment --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Metode Pembayaran
                    </h2>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-4 border-2 rounded-xl cursor-pointer transition"
                            :class="selectedPayment === 'manual_transfer' ? 'border-amber-500 bg-amber-50/50' : 'border-gray-100 hover:border-amber-200'">
                            <input type="radio" name="payment_method" value="manual_transfer"
                                {{ old('payment_method', 'manual_transfer') === 'manual_transfer' ? 'checked' : '' }}
                                @change="selectedPayment = 'manual_transfer'"
                                class="text-amber-500 focus:ring-amber-500">
                            <div>
                                <p class="font-semibold text-gray-900">Transfer Manual</p>
                                <p class="text-sm text-gray-400">Bayar via transfer bank, upload bukti setelah pesan</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border-2 rounded-xl cursor-pointer transition"
                            :class="selectedPayment === 'cod' ? 'border-amber-500 bg-amber-50/50' : 'border-gray-100 hover:border-amber-200'">
                            <input type="radio" name="payment_method" value="cod"
                                {{ old('payment_method') === 'cod' ? 'checked' : '' }}
                                @change="selectedPayment = 'cod'"
                                class="text-amber-500 focus:ring-amber-500">
                            <div>
                                <p class="font-semibold text-gray-900">COD (Bayar di Tempat)</p>
                                <p class="text-sm text-gray-400">Bayar tunai saat barang diterima</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Catatan (opsional)
                    </h2>
                    <textarea name="notes" rows="3" class="w-full border-2 border-gray-200 rounded-xl p-3.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition" placeholder="Catatan untuk pesanan...">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm sticky top-24">
                    <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Ringkasan Belanja
                    </h2>
                    <div class="space-y-3 mb-4 max-h-60 overflow-y-auto">
                        @foreach($cart as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 truncate mr-2">
                                    @if(!empty($item['bundle_name']))
                                        <span class="text-purple-600 text-[10px] font-bold bg-purple-50 px-1.5 py-0.5 rounded">PAKET</span>
                                    @endif
                                    {{ $item['name'] }}@if(!empty($item['variant_name'])) <span class="text-gray-400 text-xs">({{ $item['variant_name'] }})</span>@endif <span class="text-gray-400">×{{ $item['quantity'] }}</span>
                                </span>
                                <span class="text-gray-900 font-semibold shrink-0">Rp{{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t-2 border-gray-100 pt-4 space-y-2.5">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="text-gray-900 font-medium">Rp{{ number_format($subtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Ongkos Kirim</span>
                            <span class="text-gray-900 font-medium">
                                <span x-text="selectedCost ? 'Rp ' + selectedCost.toLocaleString('id-ID') : 'Rp15.000'">Rp{{ number_format(!empty($shippingRates) ? 0 : 15000, 0, ',', '.') }}</span>
                            </span>
                        </div>
                        @if($ppnEnabled)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">PPN {{ $ppnRate }}%</span>
                                <span class="text-gray-900 font-medium">
                                    <span x-text="'Rp ' + ppnAmount.toLocaleString('id-ID')">Rp{{ number_format($ppnAmount, 0, ',', '.') }}</span>
                                </span>
                            </div>
                        @endif
                        <template x-if="appliedCode">
                            <div class="flex justify-between text-sm">
                                <span class="text-emerald-600">Diskon Kupon</span>
                                <span class="text-emerald-600 font-medium" x-text="'- Rp ' + discountFormatted"></span>
                            </div>
                        </template>
                        <div class="flex justify-between text-lg font-extrabold border-t-2 border-gray-100 pt-3">
                            <span class="text-gray-900">Total</span>
                            <span class="text-amber-600" x-text="'Rp ' + total.toLocaleString('id-ID')">Rp{{ number_format($subtotal + (!empty($shippingRates) ? 0 : 15000) + ($ppnEnabled ? $ppnAmount : 0), 0, ',', '.') }}</span>
                        </div>
                    </div>
                    {{-- Coupon --}}
                    <div class="mt-5 pt-5 border-t-2 border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Kupon / Voucher
                        </h3>
                        <div class="flex gap-2">
                            <input type="text" x-model="code" placeholder="Masukkan kode kupon"
                                class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                            <button type="button" @click="apply" :disabled="loading"
                                class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-bold text-sm hover:bg-amber-600 disabled:opacity-50 transition whitespace-nowrap"
                                x-text="loading ? '...' : 'Pakai'"></button>
                        </div>
                        <template x-if="message">
                            <p class="text-xs mt-2" :class="valid ? 'text-emerald-600' : 'text-red-500'" x-text="message"></p>
                        </template>
                        <input type="hidden" name="coupon_code" x-model="appliedCode">
                    </div>

                    {{-- Points --}}
                    @auth
                        @php $userPoints = auth()->user()->points ?? 0; @endphp
                        @if($userPoints > 0)
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <h3 class="text-sm font-bold text-gray-900 mb-2 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                                    Poin Saya
                                    <span class="text-xs font-normal text-gray-400">({{ number_format($userPoints, 0, ',', '.') }} poin)</span>
                                </h3>
                                <div class="flex gap-2 items-center">
                                    <input type="number" name="use_points" id="use_points" min="0" max="{{ $userPoints }}" value="0"
                                        class="w-28 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none"
                                        @input.debounce="pointsUsed = Math.min(parseInt($el.value) || 0, {{ $userPoints }}, Math.floor((subtotal + selectedCost) * 0.5))">
                                    <span class="text-xs text-gray-400">= Rp <span x-text="Math.floor(pointsUsed)">{{ 0 }}</span> diskon</span>
                                    <span class="text-xs text-gray-400">(1 poin = Rp1, maks 50% total)</span>
                                </div>
                            </div>
                        @endif
                    @endauth

                    <button type="submit" class="w-full mt-5 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3.5 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="{{ $addresses->isEmpty() ? 'true' : 'false' }} || submitting">
                        <template x-if="!submitting">
                            <span class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Buat Pesanan
                            </span>
                        </template>
                        <template x-if="submitting">
                            <span class="flex items-center gap-2">
                                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Memproses...
                            </span>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function checkoutForm() {
        return {
            selectedCourier: @json(old('shipping_courier', '')),
            selectedService: @json(old('shipping_service', '')),
            selectedCost: {{ old('shipping_cost', !empty($shippingRates) ? 0 : 15000) }},
            selectedAddress: @json(old('address_id', $addresses->first()?->id ?? '')),
            selectedPayment: @json(old('payment_method', 'manual_transfer')),
            subtotal: {{ $subtotal }},
            ppnEnabled: @json($ppnEnabled),
            ppnRate: {{ $ppnRate }},
            pointsUsed: 0,
            code: '',
            appliedCode: '',
            discount: 0,
            discountFormatted: '0',
            loading: false,
            submitting: false,
            message: '',
            valid: false,

            get ppnAmount() {
                if (!this.ppnEnabled) return 0;
                return Math.round(Math.max(0, this.subtotal - this.discount) * this.ppnRate / 100);
            },

            get pointDiscount() {
                return Math.floor(this.pointsUsed);
            },

            get total() {
                return this.subtotal + this.selectedCost + this.ppnAmount - this.discount - this.pointDiscount;
            },

            selectShipping(courier, service, cost, description) {
                this.selectedCourier = courier;
                this.selectedService = service;
                this.selectedCost = parseInt(cost);
            },

            apply() {
                if (!this.code.trim()) return;
                this.loading = true;
                this.message = '';
                fetch('{{ route("checkout.apply-coupon") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ code: this.code, subtotal: {{ $subtotal }} })
                }).then(r => r.json()).then(d => {
                    this.loading = false;
                    this.message = d.message;
                    this.valid = d.valid;
                    if (d.valid) {
                        this.appliedCode = this.code;
                        this.discount = d.discount;
                        this.discountFormatted = d.discount_formatted.replace(/[^\d]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                    } else {
                        this.appliedCode = '';
                        this.discount = 0;
                        this.discountFormatted = '0';
                    }
                }).catch(() => { this.loading = false; this.message = 'Gagal memeriksa kupon.'; });
            }
        }
    }
</script>
@endpush
