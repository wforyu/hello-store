<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalAsetWidget extends BaseWidget
{
    protected static ?int $sort = 15;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Total Aset & Laba';

    protected function getStats(): array
    {
        $s = $this->assetSummary();

        return [
            Stat::make('Jumlah Produk', number_format($s['total_stock'], 0, ',', '.').' unit')
                ->description($s['total_sku'].' SKU aktif di gudang & varian')
                ->descriptionIcon('heroicon-o-cube')
                ->color('info'),

            Stat::make('Total Modal Persediaan', 'Rp'.number_format($s['modal_persediaan'], 0, ',', '.'))
                ->description($s['produk_tanpa_modal'] > 0
                    ? $s['produk_tanpa_modal'].' produk belum diisi modal (harga beli)'
                    : 'Harga beli x jumlah stok')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($s['produk_tanpa_modal'] > 0 ? 'warning' : 'gray'),

            Stat::make('Nilai Jual Persediaan', 'Rp'.number_format($s['nilai_jual_persediaan'], 0, ',', '.'))
                ->description('Kalau seluruh stok terjual harga normal')
                ->descriptionIcon('heroicon-o-tag')
                ->color('gray'),

            Stat::make('Potensi Laba dari Stok', 'Rp'.number_format($s['potensi_laba_stok'], 0, ',', '.'))
                ->description('Nilai jual dikurangi modal')
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('success'),

            Stat::make('Laba Kotor', 'Rp'.number_format($s['laba_kotor'], 0, ',', '.'))
                ->description('Pendapatan - Harga Pokok Penjualan (HPP)')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($s['laba_kotor'] >= 0 ? 'success' : 'danger'),

            Stat::make('Laba Bersih', 'Rp'.number_format($s['laba_bersih'], 0, ',', '.'))
                ->description('Laba kotor - Pengeluaran toko')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color($s['laba_bersih'] >= 0 ? 'success' : 'danger'),
        ];
    }

    /**
     * Angka mentah untuk widget + reports. Dipisah dari getStats() supaya bisa diuji.
     *
     * Catatan: HPP dihitung dari cost_price produk SAAT INI, bukan harga beli saat
     * transaksi. Ini konsisten dengan FinanceOverview & EnhancedStatsOverviewWidget.
     */
    public function assetSummary(): array
    {
        $product = Product::selectRaw('
            COALESCE(SUM(stock), 0) AS total_stock,
            COALESCE(SUM(stock * price), 0) AS retail_value,
            COALESCE(SUM(stock * cost_price), 0) AS modal_value,
            COUNT(*) AS sku_count
        ')->first();

        // Stok varian decrement terpisah dari products.stock, jadi harus dijumlahkan
        // agar tidak terlewat. Modal varian mengikuti cost_price produk induknya.
        $variant = ProductVariant::query()
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereNull('products.deleted_at')
            ->selectRaw('
                COALESCE(SUM(product_variants.stock), 0) AS total_stock,
                COALESCE(SUM(product_variants.stock * product_variants.price), 0) AS retail_value,
                COALESCE(SUM(product_variants.stock * products.cost_price), 0) AS modal_value,
                COUNT(*) AS sku_count
            ')
            ->first();

        $totalStock = (int) $product->total_stock + (int) $variant->total_stock;
        $modalPersediaan = (float) $product->modal_value + (float) $variant->modal_value;
        $nilaiJualPersediaan = (float) $product->retail_value + (float) $variant->retail_value;

        $pendapatan = (float) Order::where('payment_status', 'paid')->sum('total');
        $pengeluaran = (float) Expense::sum('amount');
        $cogs = (float) OrderItem::whereHas('order', fn ($q) => $q->where('payment_status', 'paid'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereNotNull('products.cost_price')
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as total_cogs')
            ->value('total_cogs');

        $labaKotor = $pendapatan - $cogs;

        return [
            'total_stock' => $totalStock,
            'total_sku' => (int) $product->sku_count + (int) $variant->sku_count,
            'modal_persediaan' => $modalPersediaan,
            'nilai_jual_persediaan' => $nilaiJualPersediaan,
            'potensi_laba_stok' => max(0, $nilaiJualPersediaan - $modalPersediaan),
            'produk_tanpa_modal' => Product::where(function ($query) {
                $query->whereNull('cost_price')->orWhere('cost_price', 0);
            })->count(),
            'pendapatan' => $pendapatan,
            'hpp' => $cogs,
            'pengeluaran' => $pengeluaran,
            'laba_kotor' => $labaKotor,
            'laba_bersih' => $labaKotor - $pengeluaran,
        ];
    }
}
