<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['productImages', 'brand', 'category'])
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('sku', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $childIds = $category->children()->pluck('id')->push($category->id);
                $query->whereIn('category_id', $childIds);
            }
        }

        if ($request->filled('flash_sale')) {
            $flashSale = FlashSale::find($request->flash_sale);
            if ($flashSale) {
                $flashProductIds = $flashSale->products()->pluck('products.id');
                $query->whereIn('products.id', $flashProductIds);
            }
        }

        $sort = $request->get('sort', 'terbaru');
        match ($sort) {
            'termurah' => $query->orderBy('price'),
            'termahal' => $query->orderByDesc('price'),
            'nama' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $perPage = min((int) $request->get('per_page', 12), 50);
        $products = $query->paginate($perPage);

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

    public function show(Product $product): JsonResponse
    {
        if (! $product->is_active) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Produk tidak ditemukan.',
            ], 404);
        }

        $product->load(['productImages', 'variants' => fn ($q) => $q->where('is_active', true), 'attributes', 'brand', 'category'])
            ->loadCount('approvedReviews')
            ->loadAvg('approvedReviews', 'rating');

        $reviews = $product->approvedReviews()->with('user:id,name')->latest()->get();

        $activeFlashSale = FlashSale::active()->with('products')->first();
        $flashSaleMap = $this->getFlashSaleMap($activeFlashSale);

        $data = $this->formatProduct($product, $flashSaleMap);

        $data['is_wished'] = auth()->check()
            ? Wishlist::where('user_id', auth()->id())->where('product_id', $product->id)->exists()
            : false;
        $data['description'] = $product->description;
        $data['images'] = $product->productImages->map(fn ($img) => [
            'id' => $img->id,
            'url' => '/storage/'.$img->image,
            'sort_order' => $img->sort_order,
        ]);
        $data['variants'] = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'name' => $v->name,
            'price' => (float) $v->price,
            'stock' => $v->stock,
            'weight' => (float) $v->weight,
            'image' => $v->image ? (str_starts_with($v->image, 'http') ? $v->image : '/storage/'.$v->image) : null,
            'is_active' => $v->is_active,
            'sku' => $v->sku,
        ]);
        $data['attributes'] = $product->attributes->groupBy('type')->map(fn ($items, $type) => [
            'type' => $type,
            'values' => $items->map(fn ($a) => ['label' => $a->label, 'value' => $a->value]),
        ])->values();
        $data['reviews'] = $reviews->map(fn ($r) => [
            'id' => $r->id,
            'user_name' => $r->user?->name,
            'rating' => $r->rating,
            'comment' => $r->comment,
            'created_at' => $r->created_at,
        ]);
        $data['review_stats'] = [
            'average' => round($product->approved_reviews_avg_rating ?? 0, 1),
            'total' => $product->approved_reviews_count ?? 0,
        ];

        $relatedProducts = Product::with(['productImages', 'brand'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->withCount('approvedReviews')
            ->withAvg('approvedReviews', 'rating')
            ->take(4)
            ->get()
            ->map(fn ($p) => $this->formatProduct($p, $flashSaleMap));

        $data['related_products'] = $relatedProducts;

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => null,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'image' => $cat->image ? (str_starts_with($cat->image, 'http') ? $cat->image : '/storage/'.$cat->image) : null,
                'children' => $cat->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'image' => $child->image ? (str_starts_with($child->image, 'http') ? $child->image : '/storage/'.$child->image) : null,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
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
            'image' => $product->productImages->first() ? '/storage/'.$product->productImages->first()->image : null,
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
