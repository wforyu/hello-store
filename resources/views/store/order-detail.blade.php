@extends('layouts.store')

@section('title', 'Detail Pesanan')

@section('content')
    <div class="mb-6">
        <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-600 hover:text-amber-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>
            Kembali ke Pesanan
        </a>
    </div>

    {{-- Order Header --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 mb-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-gray-900 flex items-center gap-2">
                    Pesanan #{{ $order->order_number }}
                </h1>
                <p class="text-sm text-gray-400 mt-1">{{ $order->created_at->format('d M Y, H:i') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm font-bold px-4 py-2 rounded-full w-fit
                    @if($order->status === 'pending') bg-yellow-50 text-yellow-700 border border-yellow-200
                    @elseif($order->status === 'confirmed') bg-blue-50 text-blue-700 border border-blue-200
                    @elseif($order->status === 'processing') bg-cyan-50 text-cyan-700 border border-cyan-200
                    @elseif($order->status === 'shipped') bg-purple-50 text-purple-700 border border-purple-200
                    @elseif($order->status === 'delivered') bg-emerald-50 text-emerald-700 border border-emerald-200
                    @else bg-red-50 text-red-700 border border-red-200 @endif">
                    {{ ucfirst($order->status) }}
                </span>

            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Alamat Pengiriman
                </h3>
                @if($order->address)
                    <div class="text-sm text-gray-600 bg-gray-50 rounded-xl p-4 space-y-0.5">
                        <p class="font-semibold text-gray-900">{{ $order->address->recipient }}</p>
                        <p>{{ $order->address->phone }}</p>
                        <p>{{ $order->address->street }}</p>
                        <p>{{ $order->address->city }}, {{ $order->address->province }}</p>
                    </div>
                @endif
            </div>
            <div>
                <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Pembayaran
                </h3>
                <div class="text-sm text-gray-600 bg-gray-50 rounded-xl p-4 space-y-1">
                    <p>Metode: <span class="font-medium text-gray-900">{{ $order->payment_method === 'manual_transfer' ? 'Transfer Manual' : 'COD' }}</span></p>
                    <p>Status: <span class="font-semibold {{ $order->payment_status === 'paid' ? 'text-emerald-600' : 'text-yellow-600' }}">{{ ucfirst($order->payment_status) }}</span></p>
                    @if($order->shipping_courier)
                        <p>Kurir: <span class="font-medium text-gray-900">{{ $order->shipping_courier }}</span></p>
                    @endif
                    @if($order->shipping_tracking_number)
                        <p>No Resi: <span class="font-mono font-semibold text-gray-900">{{ $order->shipping_tracking_number }}</span></p>
                    @endif
                    @if($order->shipped_at)
                        <p>Dikirim: <span class="font-medium text-gray-900">{{ $order->shipped_at->format('d M Y, H:i') }}</span></p>
                    @endif
                    @if($order->delivered_at)
                        <p>Diterima: <span class="font-medium text-gray-900">{{ $order->delivered_at->format('d M Y, H:i') }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        @if($order->status === 'shipped')
            <div class="mt-6 p-5 bg-amber-50 border-2 border-amber-200 rounded-2xl text-center">
                <svg class="w-10 h-10 text-amber-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-semibold text-amber-800 mb-3">Pesanan sedang dalam perjalanan. Jika sudah diterima, klik tombol di bawah:</p>
                <div x-data="{ showConfirm: false }">
                    <button type="button" @click="showConfirm = true"
                        class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Pesanan Diterima
                    </button>

                    {{-- Confirm Modal --}}
                    <div x-cloak x-show="showConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                        @keydown.escape.window="showConfirm = false">
                        <div @click.outside="showConfirm = false" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-emerald-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Konfirmasi Pesanan</h3>
                            <p class="text-sm text-gray-500 mb-6">Apakah kamu yakin pesanan ini sudah diterima dengan baik?</p>
                            <div class="flex gap-3">
                                <button type="button" @click="showConfirm = false"
                                    class="flex-1 px-4 py-3 border-2 border-red-200 rounded-xl text-sm font-bold text-red-700 bg-red-50 hover:bg-red-100 transition">Batal</button>
                                <form action="{{ route('orders.confirm-received', $order) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit"
                                        class="w-full px-4 py-3 rounded-xl text-sm font-bold text-white shadow-sm bg-gray-900 hover:bg-gray-800 transition">
                                        Ya, Sudah Diterima
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($order->status === 'pending')
            <div class="mt-6 p-5 bg-red-50 border-2 border-red-200 rounded-2xl text-center">
                <svg class="w-10 h-10 text-red-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <p class="text-sm font-semibold text-red-800 mb-3">Pesanan masih menunggu pembayaran. Ingin membatalkan?</p>
                <div x-data="{ showCancel: false }">
                    <button type="button" @click="showCancel = true"
                        class="inline-flex items-center gap-2 bg-white border-2 border-red-300 text-red-700 px-6 py-3 rounded-xl font-bold hover:bg-red-50 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Batalkan Pesanan
                    </button>

                    <div x-cloak x-show="showCancel" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                        @keydown.escape.window="showCancel = false">
                        <div @click.outside="showCancel = false" class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center">
                            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
                                <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Batalkan Pesanan?</h3>
                            <p class="text-sm text-gray-500 mb-6">Pesanan #{{ $order->order_number }} akan dibatalkan. Stok akan dikembalikan.</p>
                            <div class="flex gap-3">
                                <button type="button" @click="showCancel = false"
                                    class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition">Tidak</button>
                                <form action="{{ route('orders.cancel', $order) }}" method="POST" class="flex-1">
                                    @csrf
                                    <button type="submit"
                                        class="w-full px-4 py-3 rounded-xl text-sm font-bold text-white bg-red-600 hover:bg-red-700 shadow-sm transition">
                                        Ya, Batalkan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($order->notes)
            <div class="mt-4 p-4 bg-amber-50/50 border border-amber-100 rounded-xl text-sm text-amber-800 flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                <strong>Catatan:</strong> {{ $order->notes }}
            </div>
        @endif
    </div>

    {{-- Order Items --}}
    <div class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 mb-6 shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Item Pesanan
        </h2>
        <div class="divide-y divide-gray-100">
            @foreach($order->items as $item)
                <div class="py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $item->product_name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $item->quantity }} × Rp{{ number_format($item->product_price, 0, ',', '.') }}</p>
                        @if($order->status === 'delivered' && $item->product)
                            @php
                                $userReviewed = auth()->user()->reviews()->where('product_id', $item->product_id)->exists();
                            @endphp
                            @if(!$userReviewed)
                                <a href="{{ route('products.show', $item->product->slug) }}#review-form"
                                    class="inline-flex items-center gap-1 text-xs text-amber-600 hover:text-amber-700 font-medium mt-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Beri Ulasan
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-emerald-600 font-medium mt-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Sudah diulas
                                </span>
                            @endif
                        @endif
                    </div>
                    <span class="text-sm font-bold text-gray-900">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>

        <div class="border-t-2 border-gray-100 pt-4 space-y-2 text-sm mt-2">
            <div class="flex justify-between">
                <span class="text-gray-500">Subtotal</span>
                <span class="text-gray-900 font-medium">Rp{{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Ongkos Kirim</span>
                <span class="text-gray-900 font-medium">Rp{{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
            </div>
            @php
                $ppnFromOrder = 0;
                $ppnRateOrder = 0;
                if ($order->notes && str_contains($order->notes, 'PPN ')) {
                    preg_match('/PPN (\d+)%: Rp ([\d.]+)/', $order->notes, $m);
                    if ($m) {
                        $ppnRateOrder = (int) $m[1];
                        $ppnFromOrder = (int) str_replace('.', '', $m[2]);
                    }
                }
            @endphp
            @if($ppnFromOrder > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">PPN {{ $ppnRateOrder }}%</span>
                    <span class="text-gray-900 font-medium">Rp{{ number_format($ppnFromOrder, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between text-lg font-extrabold border-t-2 border-gray-100 pt-3">
                <span class="text-gray-900">Total</span>
                <span class="text-amber-600">Rp{{ number_format($order->total, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Payment Info (after upload) --}}
    @if($order->payment_method === 'manual_transfer' && $order->payment_status === 'paid' && $order->payment)
        <div class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 mb-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Informasi Pembayaran
            </h2>
            <div class="text-sm text-gray-600 bg-gray-50 rounded-xl p-4 space-y-1">
                <p>Bank: <span class="font-medium text-gray-900">{{ $order->payment->bank_name }}</span></p>
                <p>Nama Pengirim: <span class="font-medium text-gray-900">{{ $order->payment->account_name }}</span></p>
                <p>No. Rekening: <span class="font-medium text-gray-900">{{ $order->payment->account_number }}</span></p>
                @if($order->payment->paid_at)
                    <p>Dibayar: <span class="font-medium text-gray-900">{{ $order->payment->paid_at->format('d M Y, H:i') }}</span></p>
                @endif
                @if($order->payment->proof_image)
                    <div class="mt-3">
                        <a href="{{ asset('storage/' . $order->payment->proof_image) }}" target="_blank"
                            class="inline-flex items-center gap-1.5 text-amber-600 hover:text-amber-700 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Lihat Bukti Transfer
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Payment Upload --}}
    @if($order->payment_method === 'manual_transfer' && $order->payment_status !== 'paid')
        <div class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Upload Bukti Pembayaran
            </h2>
            <form action="{{ route('orders.payment', $order) }}" method="POST" enctype="multipart/form-data" class="max-w-md space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Bank Tujuan</label>
                    <input type="text" name="bank_name" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                        placeholder="BCA / Mandiri / BRI...">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Pengirim</label>
                    <input type="text" name="account_name" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. Rekening</label>
                    <input type="text" name="account_number" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Upload Bukti Transfer</label>
                    <input type="file" name="proof_image" required accept="image/*"
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-amber-50 file:to-orange-50 file:text-amber-700 hover:file:from-amber-100 hover:file:to-orange-100 transition file:cursor-pointer cursor-pointer">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3.5 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                    Upload Bukti Pembayaran
                </button>
            </form>
        </div>
    @endif
@endsection
