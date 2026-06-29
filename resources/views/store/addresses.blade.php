@extends('layouts.store')

@section('title', 'Alamat Saya')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <div class="w-1 h-7 bg-gradient-to-b from-amber-500 to-orange-500 rounded-full"></div>
            <h1 class="text-2xl lg:text-3xl font-bold text-gray-900">Alamat Saya</h1>
        </div>
        <a href="{{ route('addresses.create') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Tambah Alamat
        </a>
    </div>

    @if($addresses->isEmpty())
        <div class="text-center py-20 bg-white rounded-2xl border border-gray-100 shadow-sm">
            <svg class="h-20 w-20 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <p class="text-gray-500 text-lg mb-2">Belum ada alamat tersimpan</p>
            <p class="text-gray-400 text-sm mb-6">Tambahkan alamat untuk memudahkan pengiriman</p>
            <a href="{{ route('addresses.create') }}" class="inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-8 py-3.5 rounded-xl font-semibold hover:from-amber-600 hover:to-orange-600 shadow-sm hover:shadow transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Tambah Alamat Baru
            </a>
        </div>
    @else
        <div class="grid md:grid-cols-2 gap-4">
            @foreach($addresses as $address)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <span class="font-semibold text-gray-900">{{ $address->recipient }}</span>
                            @if($address->is_default)
                                <span class="text-[10px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded ml-1.5">UTAMA</span>
                            @endif
                            @if($address->label)
                                <span class="text-xs text-gray-400 ml-1">({{ $address->label }})</span>
                            @endif
                        </div>
                        <div class="flex gap-2 text-xs font-medium">
                            <a href="{{ route('addresses.edit', $address) }}" class="text-amber-600 hover:text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg transition">Edit</a>
                            <form action="{{ route('addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Hapus alamat ini?')">
                                @csrf @method('DELETE')
                                <button class="text-red-500 hover:text-red-600 bg-red-50 px-3 py-1.5 rounded-lg transition">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        {{ $address->phone }}
                    </p>
                    <p class="text-sm text-gray-400 mt-1.5 flex items-start gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-300 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $address->street }}, {{ $address->city }}, {{ $address->province }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif
@endsection
