<x-filament-panels::page>
    <form wire:submit="loadData">
        {{ $this->form }}
    </form>

    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500 mb-1">Total Pesanan</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($this->salesData['total_orders'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500 mb-1">Total Pendapatan</p>
                <p class="text-2xl font-bold text-emerald-600">Rp {{ number_format($this->salesData['total_revenue'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500 mb-1">Produk Terjual</p>
                <p class="text-2xl font-bold text-amber-600">{{ number_format($this->salesData['total_products'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <p class="text-sm text-gray-500 mb-1">Rata-rata Pesanan</p>
                <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($this->salesData['average_order'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Laba / Rugi</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Pendapatan</p>
                    <p class="text-xl font-bold text-emerald-600">Rp {{ number_format($this->profitData['revenue'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pengeluaran</p>
                    <p class="text-xl font-bold text-red-600">Rp {{ number_format($this->profitData['expense'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Laba Bersih</p>
                    <p class="text-xl font-bold {{ ($this->profitData['profit'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                        Rp {{ number_format($this->profitData['profit'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900">Produk Terlaris</h2>
            </div>
            @if(count($this->topProducts) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-2 font-semibold text-gray-500">#</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-500">Produk</th>
                                <th class="text-center py-3 px-2 font-semibold text-gray-500">Terjual</th>
                                <th class="text-right py-3 px-2 font-semibold text-gray-500">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->topProducts as $i => $p)
                                <tr class="border-b border-gray-50 hover:bg-gray-50">
                                    <td class="py-3 px-2 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="py-3 px-2 font-medium text-gray-900">{{ $p['name'] }}</td>
                                    <td class="py-3 px-2 text-center font-bold text-amber-600">{{ number_format($p['qty']) }}</td>
                                    <td class="py-3 px-2 text-right font-semibold">Rp {{ number_format($p['revenue'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Belum ada data penjualan.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Kategori Terlaris</h2>
            @if(count($this->topCategories) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-2 font-semibold text-gray-500">#</th>
                                <th class="text-left py-3 px-2 font-semibold text-gray-500">Kategori</th>
                                <th class="text-center py-3 px-2 font-semibold text-gray-500">Terjual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->topCategories as $i => $c)
                                <tr class="border-b border-gray-50 hover:bg-gray-50">
                                    <td class="py-3 px-2 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="py-3 px-2 font-medium text-gray-900">{{ $c['name'] }}</td>
                                    <td class="py-3 px-2 text-center font-bold text-amber-600">{{ number_format($c['qty']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Belum ada data penjualan.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Export Data</h2>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.reports.export', ['start' => $this->data['startDate'] ?? '', 'end' => $this->data['endDate'] ?? '', 'format' => 'csv']) }}"
                    class="inline-flex items-center gap-2 bg-emerald-500 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export CSV
                </a>
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 bg-gray-900 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-gray-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print PDF
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
