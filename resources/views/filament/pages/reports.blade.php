@php
    $sales = $this->salesData;
    $profit = $this->profitData;

    $printStart = $this->data['startDate'] ?? null;
    $printEnd = $this->data['endDate'] ?? null;
    $printPeriod = ($printStart && $printEnd)
        ? \Carbon\Carbon::parse($printStart)->format('d/m/Y').' – '.\Carbon\Carbon::parse($printEnd)->format('d/m/Y')
        : 'Semua Periode';

    // URL ini WAJIB dikirim lewat atribut terikat (:href), bukan `href="{{ }}"`.
    // Blade meng-escape `&` saat kompilasi atribut, lalu attribute bag Filament
    // meng-escape SEKALI LAGI -> `&amp;amp;`. Browser men-decode-nya jadi `&amp;`,
    // sehingga server menerima parameter bernama "amp;end" dan `end` hilang ->
    // ReportController melempar "Undefined array key" alias HTTP 500.
    $exportUrl = route('admin.reports.export', [
        'start' => $printStart,
        'end' => $printEnd,
        'format' => 'csv',
    ]);
@endphp

<x-filament-panels::page>
    {{--
        <style> WAJIB ada DI DALAM <x-filament-panels::page>, bukan di atasnya.
        Livewire menempelkan `wire:snapshot` / `wire:id` ke elemen PERTAMA hasil
        render komponen. Kalau <style> berada di luar, dialah yang jadi root
        komponen dan isi halaman hanya jadi saudaranya — akibatnya perubahan
        filter tidak memorph apa pun (konten terlihat "beku").

        Tombol "Print" memanggil window.print(). Tanpa aturan di bawah, yang
        tercetak adalah seluruh kerangka panel Filament (topbar, sidebar,
        notifikasi) — bukan isi laporan.
    --}}
    <style>
        .hs-print-only { display: none; }

        /* Kelas aksen: satu tempat untuk warna di layar DAN di kertas. */
        .hs-muted { color: var(--gray-500); }
        .hs-up { color: var(--success-600); }
        .hs-down { color: var(--danger-600); }
        .hs-warn { color: var(--warning-600); }
        .hs-info { color: var(--info-600); }

        @media print {
            @page { size: A4 portrait; margin: 12mm; }

            /* 1. Kerangka panel (topbar, sidebar, notifikasi, tombol) tidak dicetak.
                  PENTING: `.fi-page-header-main-ctn` JANGAN ikut disembunyikan.
                  Namanya menyesatkan — elemen itu BUKAN cuma baris judul, tapi
                  wadah flex yang MEMBUNGKUS `.fi-page-main`, jadi
                  `display: none` di situ menghapus seluruh isi halaman dan
                  hasil cetaknya kosong (halaman tetap kosong walau warnanya
                  sudah gelap). Yang benar: sembunyikan `.fi-page-header`
                  (baris judul + tombol aksi). */
            .fi-topbar-ctn,
            .fi-sidebar,
            .fi-sidebar-close-overlay,
            .fi-layout-sidebar-toggle-btn-ctn,
            .fi-no,
            .fi-header,
            .fi-page-header,
            .hs-no-print { display: none !important; }

            /* 2. Rantai tinggi & overflow Filament dinetralkan. `.fi-layout`
                  memasang `overflow-x: clip`, dan `.fi-main-ctn` memasang
                  `min-height: calc(100dvh - var(--topbar-height))`; di media
                  print keduanya memotong isi yang lebih panjang dari satu
                  halaman sehingga halaman berikutnya tidak pernah keluar. */
            html,
            body,
            .fi-body,
            .fi-layout,
            .fi-main-ctn,
            .fi-main,
            .fi-page,
            .fi-page-main,
            .fi-page-content {
                height: auto !important;
                min-height: 0 !important;
                max-height: none !important;
                overflow: visible !important;
                opacity: 1 !important;
                background: #fff !important;
            }

            /* Panel ini dark mode; `color-scheme: dark` bikin kanvas cetak gelap. */
            html,
            body { color-scheme: light !important; }

            .fi-layout,
            .fi-main-ctn { display: block !important; }

            .fi-main {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
            }

            /* 3. KUNCI "CETAK KOSONG". Filament menulis warna teksnya LANGSUNG di
                  elemen, contohnya
                  `.fi-section .fi-section-header-heading:where(.dark, .dark *) {
                  color: var(--color-white) }`.
                  Jadi mewarisi `color` dari `.hs-report-doc` TIDAK menolong:
                  heading dan angka tetap putih, dan di kertas putih hasilnya
                  kosong. Semua elemen di dalam dokumen laporan dipaksa gelap.
                  Inline style di view ini tidak memakai `!important`, sehingga
                  aturan ini menang. */
            .hs-report-doc,
            .hs-report-doc :is(h1, h2, h3, h4, h5, h6, p, span, div, td, th, li, strong, small, b, em, table, thead, tbody, tr, tfoot) {
                color: #111827 !important;
                background-color: transparent !important;
            }

            /* 3b. Aksen dikembalikan setelah dipaksa gelap: tanpa ini laba/rugi
                   dan jumlah terjual kehilangan kode warnanya di kertas. */
            .hs-report-doc .hs-muted { color: #6b7280 !important; }
            .hs-report-doc .hs-up { color: #047857 !important; }
            .hs-report-doc .hs-down { color: #b91c1c !important; }
            .hs-report-doc .hs-warn { color: #b45309 !important; }
            .hs-report-doc .hs-info { color: #0369a1 !important; }

            /* 4. Kartu tetap punya sekat, tanpa bayangan atau latar gelap. */
            .hs-report-doc .fi-section {
                background: #fff !important;
                border: 1px solid #d1d5db !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            /* 5. Tabel panjang boleh pindah halaman, judul kolom ikut terbawa,
                  dan satu baris tidak terbelah dua. */
            .hs-report-doc thead { display: table-header-group; }
            .hs-report-doc tr { break-inside: avoid; page-break-inside: avoid; }
            .hs-report-doc .hs-scroll { overflow: visible !important; }
            .hs-report-doc table tr { border-color: #d1d5db !important; }

            .hs-print-only { display: block !important; }
            .hs-print-title { font-size: 1.125rem; font-weight: 700; margin: 0 0 0.25rem; }
            .hs-print-meta { font-size: 0.75rem; color: #4b5563 !important; margin: 0 0 0.75rem; }
        }
    </style>

    <div class="hs-no-print">
        <form wire:submit="loadData">
            {{ $this->form }}
            <x-filament::button type="submit" class="mt-2">Terapkan</x-filament::button>
        </form>
    </div>

    <div class="hs-report-doc">
        <div class="hs-print-only">
            <p class="hs-print-title">Laporan Penjualan — {{ \App\Models\Setting::get('store_name', config('app.name')) }}</p>
            <p class="hs-print-meta">Periode: {{ $printPeriod }} · Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <x-filament::section>
            <x-slot name="heading">Ringkasan Penjualan</x-slot>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem">
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Total Pesanan</p>
                    <p style="font-size:1.25rem;font-weight:700">{{ number_format($sales['total_orders'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Total Pendapatan</p>
                    <p class="hs-up" style="font-size:1.25rem;font-weight:700">Rp {{ number_format($sales['total_revenue'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Produk Terjual</p>
                    <p class="hs-warn" style="font-size:1.25rem;font-weight:700">{{ number_format($sales['total_products'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Rata-rata / Pesanan</p>
                    <p class="hs-info" style="font-size:1.25rem;font-weight:700">Rp {{ number_format($sales['average_order'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Laba / Rugi</x-slot>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Pendapatan</p>
                    <p class="hs-up" style="font-size:1.125rem;font-weight:700">Rp {{ number_format($profit['revenue'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Harga Pokok (COGS)</p>
                    <p class="hs-warn" style="font-size:1.125rem;font-weight:700">Rp {{ number_format($profit['cogs'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Pengeluaran</p>
                    <p class="hs-down" style="font-size:1.125rem;font-weight:700">Rp {{ number_format($profit['expense'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="hs-muted" style="font-size:0.875rem">Laba Bersih</p>
                    <p class="{{ ($profit['profit'] ?? 0) >= 0 ? 'hs-up' : 'hs-down' }}" style="font-size:1.125rem;font-weight:700">
                        Rp {{ number_format($profit['profit'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Produk Terlaris</x-slot>
            @if(count($this->topProducts) > 0)
                <div class="hs-scroll" style="overflow-x:auto">
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
                                    <td class="hs-muted" style="padding:0.5rem 0.75rem">{{ $i + 1 }}</td>
                                    <td style="padding:0.5rem 0.75rem;font-weight:500">{{ $p['name'] }}</td>
                                    <td class="hs-warn" style="text-align:center;padding:0.5rem 0.75rem;font-weight:700">{{ number_format($p['qty']) }}</td>
                                    <td style="text-align:right;padding:0.5rem 0.75rem;font-weight:600">Rp {{ number_format($p['revenue'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="hs-muted" style="font-size:0.875rem;text-align:center;padding:1.5rem 0">Belum ada data penjualan.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Kategori Terlaris</x-slot>
            @if(count($this->topCategories) > 0)
                <div class="hs-scroll" style="overflow-x:auto">
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
                                    <td class="hs-muted" style="padding:0.5rem 0.75rem">{{ $i + 1 }}</td>
                                    <td style="padding:0.5rem 0.75rem;font-weight:500">{{ $c['name'] }}</td>
                                    <td class="hs-warn" style="text-align:center;padding:0.5rem 0.75rem;font-weight:700">{{ number_format($c['qty']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="hs-muted" style="font-size:0.875rem;text-align:center;padding:1.5rem 0">Belum ada data penjualan.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Detail Pesanan</x-slot>
            @if(count($this->orderRows) > 0)
                <div class="hs-scroll" style="overflow-x:auto">
                    <table style="width:100%;font-size:0.875rem;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid var(--gray-300)">
                                <th style="text-align:left;padding:0.5rem 0.75rem">No. Pesanan</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem">Tanggal</th>
                                <th style="text-align:left;padding:0.5rem 0.75rem">Status</th>
                                <th style="text-align:right;padding:0.5rem 0.75rem">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->orderRows as $order)
                                <tr style="border-bottom:1px solid var(--gray-200)">
                                    <td style="padding:0.5rem 0.75rem;font-weight:500">{{ $order['number'] }}</td>
                                    <td style="padding:0.5rem 0.75rem">{{ $order['date'] }}</td>
                                    <td style="padding:0.5rem 0.75rem">{{ $order['status'] }}</td>
                                    <td style="text-align:right;padding:0.5rem 0.75rem;font-weight:600">Rp {{ number_format($order['total'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="hs-muted" style="font-size:0.875rem;text-align:center;padding:1.5rem 0">Belum ada pesanan pada periode ini.</p>
            @endif
        </x-filament::section>
    </div>

    <div class="hs-no-print">
        <x-filament::section>
            <x-slot name="heading">Export</x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem">
                <x-filament::button
                    tag="a"
                    :href="$exportUrl"
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
    </div>
</x-filament-panels::page>
