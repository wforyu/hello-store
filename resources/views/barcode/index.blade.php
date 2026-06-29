@extends('layouts.app')

@section('title', 'Cetak Barcode')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Cetak Barcode Produk</h1>
        <a href="{{ route('products.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700">← Kembali</a>
    </div>

    <form action="{{ route('barcode.generate') }}" method="POST" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        @csrf

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-3">Pilih Produk</label>
            <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-xl divide-y divide-gray-100">
                @forelse($products as $product)
                    <label class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer transition">
                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}"
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $product->name }}</p>
                            <p class="text-xs text-gray-400">SKU: {{ $product->sku ?? '-' }} | Stok: {{ $product->stock }}</p>
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-gray-400 text-center py-8">Belum ada produk aktif</p>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Tipe Barcode</label>
                <select name="type" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                    <option value="code128">Code 128</option>
                    <option value="ean13">EAN-13</option>
                    <option value="qr">QR Code</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Ukuran Label</label>
                <select name="label_size" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent outline-none">
                    <option value="small">Kecil (25x15mm)</option>
                    <option value="medium">Sedang (38x25mm)</option>
                    <option value="large">Besar (50x30mm)</option>
                </select>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                class="px-6 py-2.5 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 transition shadow-sm">
                Cetak Barcode
            </button>
            <button type="button" onclick="document.querySelectorAll('input[name=\'product_ids[]\']').forEach(c => c.checked = true)"
                class="px-4 py-2.5 border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                Pilih Semua
            </button>
        </div>
    </form>
</div>
@endsection
