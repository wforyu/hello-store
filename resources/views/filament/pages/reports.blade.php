@php
    $sales = $this->salesData;
    $profit = $this->profitData;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <form wire:submit="loadData" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-wrap items-end gap-3">
                {{ $this->form }}
                <div class="shrink-0">
                    <x-filament::button type="submit">
                        Terapkan
                    </x-filament::button>
                </div>
            </div>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pesanan</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($sales['total_orders'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Pendapatan</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">Rp {{ number_format($sales['total_revenue'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Produk Terjual</p>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($sales['total_products'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Rata-rata Pesanan</p>
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">Rp {{ number_format($sales['average_order'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Laba / Rugi</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pendapatan</p>
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">Rp {{ number_format($profit['revenue'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pengeluaran</p>
                    <p class="text-lg font-bold text-red-600 dark:text-red-400 mt-0.5">Rp {{ number_format($profit['expense'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Laba Bersih</p>
                    <p class="text-lg font-bold mt-0.5 {{ ($profit['profit'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        Rp {{ number_format($profit['profit'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Produk Terlaris</h2>
            @if(count($this->topProducts) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">#</th>
                                <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Produk</th>
                                <th class="text-center py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Terjual</th>
                                <th class="text-right py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->topProducts as $i => $p)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                    <td class="py-2.5 px-3 text-xs text-gray-400 dark:text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2.5 px-3 text-sm font-medium text-gray-900 dark:text-white">{{ $p['name'] }}</td>
                                    <td class="py-2.5 px-3 text-center text-sm font-bold text-amber-600 dark:text-amber-400">{{ number_format($p['qty']) }}</td>
                                    <td class="py-2.5 px-3 text-right text-sm font-semibold text-gray-900 dark:text-white">Rp {{ number_format($p['revenue'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-6">Belum ada data penjualan.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Kategori Terlaris</h2>
            @if(count($this->topCategories) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">#</th>
                                <th class="text-left py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Kategori</th>
                                <th class="text-center py-2.5 px-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Terjual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->topCategories as $i => $c)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-900/30">
                                    <td class="py-2.5 px-3 text-xs text-gray-400 dark:text-gray-500">{{ $i + 1 }}</td>
                                    <td class="py-2.5 px-3 text-sm font-medium text-gray-900 dark:text-white">{{ $c['name'] }}</td>
                                    <td class="py-2.5 px-3 text-center text-sm font-bold text-amber-600 dark:text-amber-400">{{ number_format($c['qty']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-6">Belum ada data penjualan.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-bold text-gray-900 dark:text-white mb-3">Export Data</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.reports.export', ['start' => $this->data['startDate'] ?? '', 'end' => $this->data['endDate'] ?? '', 'format' => 'csv']) }}"
                    class="inline-flex items-center gap-1.5 bg-emerald-500 text-white px-4 py-2 rounded-lg font-semibold text-xs hover:bg-emerald-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </a>
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 bg-gray-900 dark:bg-white dark:text-gray-900 text-white px-4 py-2 rounded-lg font-semibold text-xs hover:bg-gray-800 dark:hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
