<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class WishlistController extends Controller
{
    public function toggle(Product $product): JsonResponse
    {
        $user = auth()->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $wished = false;
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $wished = true;
        }

        $count = $user->wishlists()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'wished' => $wished,
                'count' => $count,
            ],
            'message' => $wished ? 'Ditambahkan ke wishlist.' : 'Dihapus dari wishlist.',
        ]);
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();

        $wishlistIds = $user->wishlists()->pluck('product_id');

        $products = Product::with(['productImages', 'brand', 'category'])
            ->whereIn('id', $wishlistIds)
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest()
            ->paginate(10);

        $activeFlashSale = FlashSale::active()->with('products')->first();
        $flashSaleMap = $this->getFlashSaleMap($activeFlashSale);

        $products->getCollection()->transform(function ($product) use ($flashSaleMap) {
            return $this->formatProduct($product, $flashSaleMap);
        });

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => null,
        ]);
    }

    private function formatProduct(Product $product, $flashSaleMap): array
    {
        $flashData = $flashSaleMap->get($product->id);
        $price = (float) $product->price;
        $finalPrice = $flashData ? (float) $flashData['flash_price'] : $price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => $price,
            'price_formatted' => 'Rp'.number_format($price, 0, ',', '.'),
            'final_price' => $finalPrice,
            'final_price_formatted' => 'Rp'.number_format($finalPrice, 0, ',', '.'),
            'compare_price' => $product->compare_price ? (float) $product->compare_price : null,
            'compare_price_formatted' => $product->compare_price ? 'Rp'.number_format($product->compare_price, 0, ',', '.') : null,
            'stock' => $product->stock,
            'weight' => (float) $product->weight,
            'featured' => $product->featured,
            'is_digital' => $product->is_digital,
            'rating' => round($product->approved_reviews_avg_rating ?? 0, 1),
            'review_count' => (int) ($product->approved_reviews_count ?? 0),
            'image' => $product->main_image,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
            'flash_sale' => $flashData ? [
                'flash_sale_id' => $flashData['flash_sale_id'],
                'flash_sale_name' => $flashData['flash_sale_name'],
                'discount_type' => $flashData['discount_type'],
                'discount_value' => $flashData['discount_value'],
                'discount_percent' => $flashData['discount_type'] === 'percentage' ? $flashData['discount_value'] : round((1 - $finalPrice / $price) * 100),
            ] : null,
            'created_at' => $product->created_at,
        ];
    }

    private function getFlashSaleMap(?FlashSale $activeFlashSale): Collection
    {
        if (! $activeFlashSale) {
            return collect();
        }

        $map = [];
        foreach ($activeFlashSale->products as $product) {
            $pivot = $product->pivot;
            $price = (float) $product->price;
            if ($pivot->discount_type === 'percentage') {
                $flashPrice = max(0, $price - ($price * $pivot->discount_value / 100));
            } else {
                $flashPrice = max(0, $price - (float) $pivot->discount_value);
            }
            $map[$product->id] = [
                'flash_sale_id' => $activeFlashSale->id,
                'flash_sale_name' => $activeFlashSale->name,
                'discount_type' => $pivot->discount_type,
                'discount_value' => $pivot->discount_value,
                'flash_price' => round($flashPrice),
            ];
        }

        return collect($map);
    }
}
