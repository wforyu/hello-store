<?php

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Product::whereNotNull('images')->each(function (Product $product) {
            $images = $product->getRawOriginal('images');
            $images = $images ? json_decode($images, true) : [];

            foreach ($images as $index => $image) {
                if (is_string($image) && $image !== '') {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image' => $image,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('images')->nullable();
        });

        ProductImage::all()->groupBy('product_id')->each(function ($images, $productId) {
            $product = Product::find($productId);
            if ($product) {
                $product->setAttribute('images', $images->pluck('image')->values()->toArray());
                $product->save();
            }
        });
    }
};
