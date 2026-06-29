@extends('layouts.app')

@section('title', 'Riwayat Shift')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Riwayat Shift</h1>
        <a href="{{ route('pos.index') }}" class="text-sm font-medium text-amber-600 hover:text-amber-700">← Kembali ke POS</a>
    </div>

    @if($shifts->count() > 0)
        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left py-4 px-6 font-semibold text-gray-500">Tanggal</th>
                            <th class="text-left py-4 px-6 font-semibold text-gray-500">Kasir</th>
                            <th class="text-right py-4 px-6 font-semibold text-gray-500">Saldo Awal</th>
                            <th class="text-right py-4 px-6 font-semibold text-gray-500">Saldo Akhir</th>
                            <th class="text-right py-4 px-6 font-semibold text-gray-500">Selisih</th>
                            <th class="text-center py-4 px-6 font-semibold text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shifts as $shift)
                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                <td class="py-4 px-6">
                                    <p class="font-medium text-gray-900">{{ $shift->opened_at->format('d M Y') }}</p>
                                    <p class="text-xs text-gray-400">{{ $shift->opened_at->format('H:i') }} - {{ $shift->closed_at?->format('H:i') ?? 'Berlangsung' }}</p>
                                </td>
                                <td class="py-4 px-6 text-gray-700">{{ $shift->user->name }}</td>
                                <td class="py-4 px-6 text-right font-medium">Rp {{ number_format($shift->opening_balance, 0, ',', '.') }}</td>
                                <td class="py-4 px-6 text-right font-medium">Rp {{ number_format($shift->closing_balance ?? 0, 0, ',', '.') }}</td>
                                <td class="py-4 px-6 text-right">
                                    @if($shift->difference !== null)
                                        <span class="font-bold {{ $shift->difference >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                            {{ $shift->difference >= 0 ? '+' : '' }}Rp {{ number_format($shift->difference, 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-center">
                                    @if($shift->isOpen())
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                            Selesai
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">
            {{ $shifts->links() }}
        </div>
    @else
        <div class="text-center py-16 bg-white rounded-2xl border border-gray-100">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h2 class="text-lg font-semibold text-gray-700 mb-2">Belum Ada Riwayat Shift</h2>
            <p class="text-sm text-gray-400">Buka shift kamu dari halaman POS.</p>
        </div>
    @endif
</div>
@endsection
