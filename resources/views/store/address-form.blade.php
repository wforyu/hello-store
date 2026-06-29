@extends('layouts.store')

@section('title', isset($address) ? 'Edit Alamat' : 'Tambah Alamat')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">{{ isset($address) ? 'Edit Alamat' : 'Tambah Alamat' }}</h1>
        </div>

        <form action="{{ isset($address) ? route('addresses.update', $address) : route('addresses.store') }}" method="POST"
            class="bg-white rounded-2xl border border-gray-100 p-6 lg:p-8 space-y-5 shadow-sm">
            @csrf
            @if(isset($address)) @method('PUT') @endif

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Label (opsional)</label>
                <input type="text" name="label" value="{{ old('label', $address->label ?? '') }}"
                    class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                    placeholder="Rumah / Kantor / dll">
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Penerima</label>
                    <input type="text" name="recipient" value="{{ old('recipient', $address->recipient ?? '') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">No. HP</label>
                    <input type="text" name="phone" value="{{ old('phone', $address->phone ?? '') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alamat Lengkap</label>
                <textarea name="street" rows="2" required
                    class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">{{ old('street', $address->street ?? '') }}</textarea>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kota</label>
                    <input type="text" name="city" value="{{ old('city', $address->city ?? '') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Provinsi</label>
                    <input type="text" name="province" value="{{ old('province', $address->province ?? '') }}" required
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kode Pos</label>
                    <input type="text" name="postal_code" value="{{ old('postal_code', $address->postal_code ?? '') }}"
                        class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition">
                </div>
            </div>

            <div>
                <textarea name="notes" rows="2"
                    class="w-full border-2 border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                    placeholder="Catatan untuk kurir (opsional)">{{ old('notes', $address->notes ?? '') }}</textarea>
            </div>

            <label class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-amber-50/50 transition">
                <input type="checkbox" name="is_default" value="1" {{ old('is_default', $address->is_default ?? false) ? 'checked' : '' }}
                    class="rounded text-amber-500 focus:ring-amber-500 w-4 h-4">
                <span class="text-sm font-medium text-gray-700">Jadikan alamat utama</span>
            </label>

            <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3.5 rounded-xl font-bold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ isset($address) ? 'Simpan Perubahan' : 'Simpan Alamat' }}
            </button>
        </form>
    </div>
@endsection
