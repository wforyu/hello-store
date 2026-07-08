<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $start = $validated['start'] ? Carbon::parse($validated['start'])->startOfDay() : now()->startOfMonth();
        $end = $validated['end'] ? Carbon::parse($validated['end'])->endOfDay() : now()->endOfDay();

        $orders = Order::whereBetween('created_at', [$start, $end])
            ->where('payment_status', 'paid')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laporan-penjualan-'.now()->format('Ymd').'.csv"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['No. Pesanan', 'Tanggal', 'Status', 'Total', 'Metode Bayar']);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->order_number,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->status,
                    $order->total,
                    $order->payment_method === 'manual_transfer' ? 'Transfer Manual' : 'COD',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
