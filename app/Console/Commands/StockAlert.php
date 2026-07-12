<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Product;
use Illuminate\Console\Command;

class StockAlert extends Command
{
    protected $signature = 'stock:alert';

    protected $description = 'Notify admins about low stock products';

    public function handle(): void
    {
        $products = Product::where('is_active', true)
            ->where('stock', '<=', 5)
            ->get();

        foreach ($products as $product) {
            Notification::createForAdmins(
                'stock_alert',
                "Stok '{$product->name}' hampir habis",
                "Sisa stok: {$product->stock}",
                'heroicon-o-exclamation-triangle',
                null,
            );
        }

        $this->info('Created '.$products->count().' stock alert notifications');
    }
}
