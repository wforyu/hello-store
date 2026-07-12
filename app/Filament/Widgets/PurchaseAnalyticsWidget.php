<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use DB;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PurchaseAnalyticsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalPO = PurchaseOrder::count();

        $nilaiPembelian = PurchaseOrder::sum('total');

        $outstandingPO = PurchaseOrder::whereNotIn('status', ['received', 'cancelled'])->count();

        $supplierTerbaik = Supplier::select('suppliers.name', DB::raw('SUM(purchase_orders.total) as total_value'))
            ->join('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_value')
            ->first();

        $barangTerbanyak = PurchaseOrderItem::select('product_name', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->first();

        $supplierLabel = $supplierTerbaik
            ? $supplierTerbaik->name.' (Rp '.number_format((float) $supplierTerbaik->total_value, 0, ',', '.').')'
            : 'Tidak ada data';

        $barangLabel = $barangTerbanyak
            ? $barangTerbanyak->product_name.' ('.(int) $barangTerbanyak->total_qty.' pcs)'
            : 'Tidak ada data';

        return [
            Stat::make('Total PO', (string) $totalPO)
                ->description('Semua purchase order')
                ->descriptionIcon('heroicon-o-clipboard-document-list')
                ->color('info'),

            Stat::make('Supplier Terbaik', $supplierLabel)
                ->description('Nilai pembelian tertinggi')
                ->descriptionIcon('heroicon-o-trophy')
                ->color('success'),

            Stat::make('Barang Terbanyak Dibeli', $barangLabel)
                ->description('Total kuantitas tertinggi')
                ->descriptionIcon('heroicon-o-cube')
                ->color('warning'),

            Stat::make('Nilai Pembelian', 'Rp '.number_format((float) $nilaiPembelian, 0, ',', '.'))
                ->description('Total seluruh PO')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('primary'),

            Stat::make('Outstanding PO', (string) $outstandingPO)
                ->description('Draft / Ordered / Partial')
                ->descriptionIcon('heroicon-o-clock')
                ->color('danger'),
        ];
    }
}
