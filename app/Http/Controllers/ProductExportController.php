<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductExportController extends Controller
{
    public function export()
    {
        $products = Product::with('category', 'brand')
            ->select([
                'id', 'category_id', 'brand_id', 'name', 'slug', 'description',
                'price', 'compare_price', 'cost_price', 'stock', 'sku', 'weight',
                'is_active', 'featured', 'is_digital',
                'meta_title', 'meta_description',
            ])
            ->get();

        $filename = 'products_export_'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID', 'Kategori ID', 'Brand ID', 'Nama', 'Slug', 'Deskripsi',
                'Harga', 'Harga Banding', 'Harga Modal', 'Stok', 'SKU', 'Berat',
                'Aktif', 'Unggulan', 'Digital',
                'Meta Title', 'Meta Description',
            ]);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->id,
                    $product->category_id,
                    $product->brand_id,
                    $product->name,
                    $product->slug,
                    $product->description,
                    $product->price,
                    $product->compare_price,
                    $product->cost_price,
                    $product->stock,
                    $product->sku,
                    $product->weight,
                    $product->is_active ? '1' : '0',
                    $product->featured ? '1' : '0',
                    $product->is_digital ? '1' : '0',
                    $product->meta_title,
                    $product->meta_description,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
