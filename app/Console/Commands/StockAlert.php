<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class StockAlert extends Command
{
    protected $signature = 'stock:alert {--threshold=5 : Stok minimum sebelum notifikasi}';

    protected $description = 'Notify admins about low stock and out-of-stock products';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $lowStockProducts = Product::where('stock', '>', 0)
            ->where('stock', '<=', $threshold)
            ->where('is_active', true)
            ->get();

        $outOfStockProducts = Product::where('stock', 0)
            ->where('is_active', true)
            ->get();

        $lowStockVariants = ProductVariant::where('stock', '>', 0)
            ->where('stock', '<=', $threshold)
            ->where('is_active', true)
            ->with('product')
            ->get();

        $outOfStockVariants = ProductVariant::where('stock', 0)
            ->where('is_active', true)
            ->with('product')
            ->get();

        $totalLow = $lowStockProducts->count() + $lowStockVariants->count();
        $totalOut = $outOfStockProducts->count() + $outOfStockVariants->count();

        if ($totalLow === 0 && $totalOut === 0) {
            $this->info('Semua stok aman.');

            return Command::SUCCESS;
        }

        if ($totalOut > 0) {
            $names = $outOfStockProducts->pluck('name')->take(5)->implode(', ');
            $variantNames = $outOfStockVariants->pluck('product.name')->take(5)->implode(', ');
            $allNames = array_filter(array_merge(explode(', ', $names), explode(', ', $variantNames)));
            $displayNames = implode(', ', array_slice($allNames, 0, 5));
            $more = count($allNames) > 5 ? ' dan '.(count($allNames) - 5).' lainnya' : '';

            $body = "{$totalOut} produk/stok habis: {$displayNames}{$more}";

            Notification::createForAdmins(
                'stock_out',
                'Stok Habis!',
                $body,
                'heroicon-o-x-circle',
                '/admin/resources/products',
            );

            $this->warn("STOK HABIS: {$totalOut} produk");
        }

        if ($totalLow > 0) {
            $names = $lowStockProducts->pluck('name')->take(5)->implode(', ');
            $variantNames = $lowStockVariants->pluck('product.name')->take(5)->implode(', ');
            $allNames = array_filter(array_merge(explode(', ', $names), explode(', ', $variantNames)));
            $displayNames = implode(', ', array_slice($allNames, 0, 5));
            $more = count($allNames) > 5 ? ' dan '.(count($allNames) - 5).' lainnya' : '';

            $body = "{$totalLow} produk stok menipis (≤ {$threshold}): {$displayNames}{$more}";

            Notification::createForAdmins(
                'stock_low',
                'Stok Menipis',
                $body,
                'heroicon-o-exclamation-triangle',
                '/admin/resources/products',
            );

            $this->warn("STOK MENIPIS: {$totalLow} produk");
        }

        return Command::SUCCESS;
    }
}
