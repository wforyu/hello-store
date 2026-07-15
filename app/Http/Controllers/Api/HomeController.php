<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductBundle;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::active()
            ->where('type', 'announcement')
            ->limit(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'description' => $b->description,
                'image' => $b->image ? '/storage/'.$b->image : null,
                'link' => $b->link,
                'link_label' => $b->link_label,
            ]);

        $popup = Banner::active()
            ->where('type', 'popup')
            ->first();

        $flashSale = FlashSale::active()->with(['products' => fn ($q) => $q->with('productImages')])->first();
        $flashSaleData = null;
        if ($flashSale) {
            $flashSaleProducts = $flashSale->products->take(8)->map(function ($product) {
                $pivot = $product->pivot;
                $price = (float) $product->price;
                if ($pivot->discount_type === 'percentage') {
                    $flashPrice = max(0, $price - ($price * $pivot->discount_value / 100));
                    $discountPercent = $pivot->discount_value;
                } else {
                    $flashPrice = max(0, $price - (float) $pivot->discount_value);
                    $discountPercent = $price > 0 ? round((1 - $flashPrice / $price) * 100) : 0;
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => $price,
                    'price_formatted' => 'Rp'.number_format($price, 0, ',', '.'),
                    'flash_price' => round($flashPrice),
                    'flash_price_formatted' => 'Rp'.number_format(round($flashPrice), 0, ',', '.'),
                    'discount_percent' => $discountPercent,
                    'stock' => $product->stock,
                    'max_qty' => $pivot->max_qty,
                    'sold_qty' => $pivot->sold_qty ?? 0,
                    'image' => $product->main_image,
                ];
            });

            $flashSaleData = [
                'id' => $flashSale->id,
                'name' => $flashSale->name,
                'description' => $flashSale->description,
                'start_time' => $flashSale->start_time?->toIso8601String(),
                'end_time' => $flashSale->end_time?->toIso8601String(),
                'products' => $flashSaleProducts,
            ];
        }

        $featuredProducts = Product::with(['productImages', 'brand', 'category'])
            ->where('is_active', true)
            ->where('featured', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->take(8)
            ->get()
            ->map(fn ($p) => $this->formatProduct($p));

        $latestProducts = Product::with(['productImages', 'brand', 'category'])
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($p) => $this->formatProduct($p));

        $bundles = ProductBundle::where('is_active', true)
            ->with(['products' => fn ($q) => $q->where('is_active', true)->with('productImages')])
            ->take(4)
            ->get()
            ->map(fn ($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'slug' => $bundle->slug,
                'description' => $bundle->description,
                'bundle_price' => (float) $bundle->bundle_price,
                'bundle_price_formatted' => 'Rp'.number_format($bundle->bundle_price, 0, ',', '.'),
                'total_original_price' => (float) $bundle->total_original_price,
                'total_original_price_formatted' => 'Rp'.number_format($bundle->total_original_price, 0, ',', '.'),
                'savings' => max(0, (float) $bundle->total_original_price - (float) $bundle->bundle_price),
                'savings_formatted' => 'Rp'.number_format(max(0, (float) $bundle->total_original_price - (float) $bundle->bundle_price), 0, ',', '.'),
                'image' => $bundle->image ? '/storage/'.$bundle->image : null,
                'product_count' => $bundle->products->count(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'banners' => $banners,
                'popup' => $popup ? [
                    'id' => $popup->id,
                    'title' => $popup->title,
                    'description' => $popup->description,
                    'image' => $popup->image ? '/storage/'.$popup->image : null,
                    'link' => $popup->link,
                    'link_label' => $popup->link_label,
                ] : null,
                'flash_sale' => $flashSaleData,
                'featured_products' => $featuredProducts,
                'latest_products' => $latestProducts,
                'bundles' => $bundles,
            ],
        ]);
    }

    private function formatProduct(Product $product): array
    {
        $price = (float) $product->price;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $price,
            'price_formatted' => 'Rp'.number_format($price, 0, ',', '.'),
            'compare_price' => $product->compare_price ? (float) $product->compare_price : null,
            'stock' => $product->stock,
            'featured' => $product->featured,
            'rating' => round($product->approved_reviews_avg_rating ?? 0, 1),
            'review_count' => (int) ($product->approved_reviews_count ?? 0),
            'image' => $product->main_image,
            'category' => $product->category?->name,
            'brand' => $product->brand?->name,
        ];
    }
}
