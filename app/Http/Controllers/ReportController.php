<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Export CSV laporan.
     *
     * Isinya WAJIB sama dengan halaman `/admin/reports`: Ringkasan Penjualan,
     * Laba/Rugi, Produk Terlaris, Kategori Terlaris — baru di bawahnya Detail
     * Pesanan. Dulu endpoint ini cuma membuang baris order mentah, jadi admin
     * yang klik "Export CSV" merasa dapat file yang bukan laporannya.
     *
     * Angkanya datang dari SalesReportService, sumber yang sama dengan
     * halaman laporan, supaya tidak bisa melenceng satu sama lain.
     */
    public function export(Request $request, SalesReportService $reports)
    {
        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'format' => 'nullable|in:csv',
        ]);

        // Jangan akses $validated['start'] langsung: parameter yang tidak dikirim
        // (atau ter-mangle jadi `amp;start` karena `&` ter-escape) tidak punya key
        // sama sekali, dan Laravel mengubah warning PHP jadi HTTP 500.
        $start = filled($validated['start'] ?? null)
            ? Carbon::parse($validated['start'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = filled($validated['end'] ?? null)
            ? Carbon::parse($validated['end'])->endOfDay()
            : now()->endOfDay();

        if ($end->lt($start)) {
            // Rentang terbalik: tukar, jangan balas error tanpa pesan yang
            // terlihat admin (halaman ini di-load lewat tautan GET).
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $report = $reports->build($start, $end);

        $filename = 'laporan-penjualan-'.$start->format('Ymd').'-'.$end->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report, $start, $end) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM supaya Excel membaca nama produk beraksen dengan benar.
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            $money = fn ($value) => number_format((float) $value, 0, ',', '.');

            fputcsv($file, ['Laporan Penjualan - '.config('app.name')]);
            fputcsv($file, ['Periode', $start->format('d/m/Y').' s/d '.$end->format('d/m/Y')]);
            fputcsv($file, []);

            // --- Ringkasan Penjualan -----------------------------------------
            fputcsv($file, ['Ringkasan Penjualan']);
            fputcsv($file, ['Total Pesanan', $report['sales']['total_orders']]);
            fputcsv($file, ['Total Pendapatan', $money($report['sales']['total_revenue'])]);
            fputcsv($file, ['Produk Terjual', $report['sales']['total_products']]);
            fputcsv($file, ['Rata-rata / Pesanan', $money($report['sales']['average_order'])]);
            fputcsv($file, []);

            // --- Laba / Rugi --------------------------------------------------
            fputcsv($file, ['Laba / Rugi']);
            fputcsv($file, ['Pendapatan', $money($report['profit']['revenue'])]);
            fputcsv($file, ['Harga Pokok (COGS)', $money($report['profit']['cogs'])]);
            fputcsv($file, ['Pengeluaran', $money($report['profit']['expense'])]);
            fputcsv($file, ['Laba Bersih', $money($report['profit']['profit'])]);
            fputcsv($file, []);

            // --- Produk Terlaris ---------------------------------------------
            fputcsv($file, ['Produk Terlaris']);
            fputcsv($file, ['#', 'Produk', 'Terjual', 'Pendapatan']);
            foreach ($report['topProducts'] as $i => $row) {
                fputcsv($file, [$i + 1, $row['name'], $row['qty'], $money($row['revenue'])]);
            }
            if ($report['topProducts'] === []) {
                fputcsv($file, ['Belum ada data penjualan.']);
            }
            fputcsv($file, []);

            // --- Kategori Terlaris -------------------------------------------
            fputcsv($file, ['Kategori Terlaris']);
            fputcsv($file, ['#', 'Kategori', 'Terjual']);
            foreach ($report['topCategories'] as $i => $row) {
                fputcsv($file, [$i + 1, $row['name'], $row['qty']]);
            }
            if ($report['topCategories'] === []) {
                fputcsv($file, ['Belum ada data penjualan.']);
            }
            fputcsv($file, []);

            // --- Detail Pesanan (dulu isi satu-satunya file ini) --------------
            fputcsv($file, ['Detail Pesanan']);
            fputcsv($file, ['No. Pesanan', 'Tanggal', 'Status', 'Total', 'Metode Bayar']);
            foreach ($report['orders'] as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-',
                    $order->status,
                    $money($order->total),
                    $order->payment_method === 'manual_transfer' ? 'Transfer Manual' : 'COD',
                ]);
            }

            fclose($file);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
