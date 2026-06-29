@php
    $sales = $this->salesData;
    $profit = $this->profitData;
@endphp

<x-filament-panels::page>
    <form wire:submit="loadData">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-2">Terapkan</x-filament::button>
    </form>

    <x-filament::section>
        <x-slot name="heading">Ringkasan Penjualan</x-slot>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem">
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Total Pesanan</p>
                <p style="font-size:1.25rem;font-weight:700">{{ number_format($sales['total_orders'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Total Pendapatan</p>
                <p style="font-size:1.25rem;font-weight:700;color:var(--success-600)">Rp {{ number_format($sales['total_revenue'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Produk Terjual</p>
                <p style="font-size:1.25rem;font-weight:700;color:var(--warning-600)">{{ number_format($sales['total_products'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Rata-rata Pesanan</p>
                <p style="font-size:1.25rem;font-weight:700;color:var(--info-600)">Rp {{ number_format($sales['average_order'] ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Laba / Rugi</x-slot>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem">
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Pendapatan</p>
                <p style="font-size:1.125rem;font-weight:700;color:var(--success-600)">Rp {{ number_format($profit['revenue'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Pengeluaran</p>
                <p style="font-size:1.125rem;font-weight:700;color:var(--danger-600)">Rp {{ number_format($profit['expense'] ?? 0, 0, ',', '.') }}</p>
            </div>
            <div>
                <p style="font-size:0.875rem;color:var(--gray-500)">Laba Bersih</p>
                <p style="font-size:1.125rem;font-weight:700;color:{{ ($profit['profit'] ?? 0) >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }}">
                    Rp {{ number_format($profit['profit'] ?? 0, 0, ',', '.') }}
                </p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Produk Terlaris</x-slot>
        @if(count($this->topProducts) > 0)
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:0.875rem;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid var(--gray-300)">
                            <th style="text-align:left;padding:0.5rem 0.75rem">#</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem">Produk</th>
                            <th style="text-align:center;padding:0.5rem 0.75rem">Terjual</th>
                            <th style="text-align:right;padding:0.5rem 0.75rem">Pendapatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->topProducts as $i => $p)
                            <tr style="border-bottom:1px solid var(--gray-200)">
                                <td style="padding:0.5rem 0.75rem;color:var(--gray-400)">{{ $i + 1 }}</td>
                                <td style="padding:0.5rem 0.75rem;font-weight:500">{{ $p['name'] }}</td>
                                <td style="text-align:center;padding:0.5rem 0.75rem;font-weight:700;color:var(--warning-600)">{{ number_format($p['qty']) }}</td>
                                <td style="text-align:right;padding:0.5rem 0.75rem;font-weight:600">Rp {{ number_format($p['revenue'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="font-size:0.875rem;text-align:center;padding:1.5rem 0;color:var(--gray-400)">Belum ada data penjualan.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Kategori Terlaris</x-slot>
        @if(count($this->topCategories) > 0)
            <div style="overflow-x:auto">
                <table style="width:100%;font-size:0.875rem;border-collapse:collapse">
                    <thead>
                        <tr style="border-bottom:1px solid var(--gray-300)">
                            <th style="text-align:left;padding:0.5rem 0.75rem">#</th>
                            <th style="text-align:left;padding:0.5rem 0.75rem">Kategori</th>
                            <th style="text-align:center;padding:0.5rem 0.75rem">Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->topCategories as $i => $c)
                            <tr style="border-bottom:1px solid var(--gray-200)">
                                <td style="padding:0.5rem 0.75rem;color:var(--gray-400)">{{ $i + 1 }}</td>
                                <td style="padding:0.5rem 0.75rem;font-weight:500">{{ $c['name'] }}</td>
                                <td style="text-align:center;padding:0.5rem 0.75rem;font-weight:700;color:var(--warning-600)">{{ number_format($c['qty']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="font-size:0.875rem;text-align:center;padding:1.5rem 0;color:var(--gray-400)">Belum ada data penjualan.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Export Data</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
            <x-filament::button
                tag="a"
                href="{{ route('admin.reports.export', ['start' => $this->data['startDate'] ?? '', 'end' => $this->data['endDate'] ?? '', 'format' => 'csv']) }}"
                color="success"
                icon="heroicon-o-arrow-down-tray"
            >
                Export CSV
            </x-filament::button>
            <x-filament::button
                color="gray"
                icon="heroicon-o-printer"
                onclick="window.print()"
            >
                Print
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
