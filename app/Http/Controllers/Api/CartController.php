<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    private function getOrCreateCart(): Cart
    {
        return Cart::firstOrCreate(['user_id' => auth()->id()]);
    }

    public function count(): JsonResponse
    {
        $cart = $this->getOrCreateCart();

        return response()->json([
            'success' => true,
            'data' => ['count' => $cart->items()->sum('quantity')],
        ]);
    }

    public function index(): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $cart->load(['items.product.productImages', 'items.product.brand', 'items.bundle']);

        $items = $cart->items->map(function ($item) {
            $product = $item->product;
            if (! $product || ! $product->is_active) {
                return null;
            }

            $price = (float) $item->price;
            $stock = $product->stock;

            if ($item->product_variant_id) {
                $variant = $item->variant;
                if ($variant) {
                    $price = (float) ($variant->price ?? $product->price);
                    $stock = $variant->stock;
                }
            }

            return [
                'id' => $item->id,
                'product_id' => $product->id,
                'variant_id' => $item->product_variant_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $price,
                'price_formatted' => 'Rp'.number_format($price, 0, ',', '.'),
                'quantity' => $item->quantity,
                'stock' => $stock,
                'subtotal' => $price * $item->quantity,
                'subtotal_formatted' => 'Rp'.number_format($price * $item->quantity, 0, ',', '.'),
                'image' => $product->main_image,
                'is_active' => $product->is_active,
                'bundle_id' => $item->bundle_id,
                'bundle_name' => $item->bundle?->name,
            ];
        })->filter()->values();

        $subtotal = $items->sum('subtotal');

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'subtotal' => $subtotal,
                'subtotal_formatted' => 'Rp'.number_format($subtotal, 0, ',', '.'),
                'total_items' => $items->sum('quantity'),
            ],
            'message' => null,
        ]);
    }

    public function add(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'quantity' => 'integer|min:1|max:100',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $variantId = $request->integer('variant_id');
        $qty = $request->integer('quantity', 1);
        $variant = null;

        if ($variantId) {
            $variant = $product->variants()->where('is_active', true)->find($variantId);
            if (! $variant) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Varian tidak ditemukan.',
                ], 404);
            }

            if ($variant->stock < 1) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Stok varian habis.',
                ], 422);
            }

            $effectiveStock = $variant->stock;
            $price = $variant->price ?? $product->price;
        } else {
            if ($product->stock < 1) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'Stok produk habis.',
                ], 422);
            }

            $effectiveStock = $product->stock;
            $price = $product->price;
        }

        $cart = $this->getOrCreateCart();
        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId ?: null)
            ->first();

        if ($existingItem) {
            $newQty = min($existingItem->quantity + $qty, $effectiveStock);
            $existingItem->update(['quantity' => $newQty, 'price' => $price]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variantId ?: null,
                'quantity' => min($qty, $effectiveStock),
                'price' => $price,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Produk ditambahkan ke keranjang.',
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:cart_items,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
        ]);

        $cart = $this->getOrCreateCart();
        $cartItemIds = $cart->items()->pluck('id')->toArray();

        DB::transaction(function () use ($request, $cartItemIds, $cart) {
            $cart->load(['items.product', 'items.variant']);

            foreach ($request->items as $itemData) {
                if (! in_array($itemData['id'], $cartItemIds)) {
                    continue;
                }

                $cartItem = $cart->items->firstWhere('id', $itemData['id']);
                if (! $cartItem || ! $cartItem->product) {
                    continue;
                }

                $qty = (int) $itemData['quantity'];
                $maxStock = $cartItem->product->stock;

                if ($cartItem->product_variant_id && $cartItem->variant) {
                    $maxStock = $cartItem->variant->stock;
                }

                $cartItem->update([
                    'quantity' => min($qty, $maxStock),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Keranjang diperbarui.',
        ]);
    }

    public function remove($productId): JsonResponse
    {
        $cart = $this->getOrCreateCart();
        $item = $cart->items()->where('product_id', $productId)->orWhere('id', $productId)->first();

        if (! $item) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Item tidak ditemukan di keranjang.',
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Produk dihapus dari keranjang.',
        ]);
    }
}
