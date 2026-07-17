<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ProductBundle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends Controller
{
    public function show(ProductBundle $bundle): JsonResponse
    {
        $bundle->load(['products.productImages', 'products.brand']);

        if (! $bundle->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Paket tidak tersedia.',
            ], 404);
        }

        $products = $bundle->products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => (float) $p->price,
            'price_formatted' => 'Rp'.number_format($p->price, 0, ',', '.'),
            'stock' => $p->stock,
            'image' => $p->main_image,
            'quantity' => (int) ($p->pivot->quantity ?? 1),
            'brand' => $p->brand?->name,
        ]);

        $originalPrice = $bundle->getCalculatedOriginalPrice();
        $savings = max(0, $originalPrice - (float) $bundle->bundle_price);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'bundle_price' => (float) $bundle->bundle_price,
                'bundle_price_formatted' => 'Rp'.number_format($bundle->bundle_price, 0, ',', '.'),
                'total_original_price' => $originalPrice,
                'total_original_price_formatted' => 'Rp'.number_format($originalPrice, 0, ',', '.'),
                'savings' => $savings,
                'savings_formatted' => 'Rp'.number_format($savings, 0, ',', '.'),
                'image' => $bundle->image ? '/storage/'.$bundle->image : null,
                'products' => $products,
            ],
        ]);
    }

    public function addToCart(Request $request, ProductBundle $bundle): JsonResponse
    {
        $bundle->load('products');

        if (! $bundle->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Paket tidak tersedia.',
            ], 404);
        }

        if ($bundle->products->isEmpty()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Paket ini tidak memiliki produk!',
            ], 422);
        }

        $originalTotal = $bundle->getCalculatedOriginalPrice();
        $bundlePrice = (float) $bundle->bundle_price;
        $discountPercent = $originalTotal > 0 ? ($originalTotal - $bundlePrice) / $originalTotal : 0;

        $cart = Cart::firstOrCreate(['user_id' => auth()->id()]);
        $added = 0;

        foreach ($bundle->products as $product) {
            if (! $product->is_active || $product->stock < 1) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => "Stok '{$product->name}' dalam paket ini habis!",
                ], 422);
            }

            $qty = (int) ($product->pivot->quantity ?? 1);
            $itemPrice = $discountPercent > 0
                ? round($product->price * (1 - $discountPercent))
                : (float) $product->price;

            $existingItem = $cart->items()
                ->where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->first();

            if ($existingItem) {
                $existingItem->update(['quantity' => min($existingItem->quantity + $qty, $product->stock), 'bundle_id' => $bundle->id]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => min($qty, $product->stock),
                    'price' => $itemPrice,
                    'bundle_id' => $bundle->id,
                ]);
            }
            $added++;
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => "Paket '{$bundle->name}' ditambahkan ke keranjang ({$added} produk).",
        ]);
    }
}
