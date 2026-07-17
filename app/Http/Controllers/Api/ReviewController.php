<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        $isNew = $review->wasRecentlyCreated;

        Notification::createForAdmins(
            'review',
            'Ulasan Baru',
            $request->user()->name.' memberikan ulasan bintang '.$validated['rating'].' untuk produk '.$product->name,
            'star',
            '/admin/resources/products/'.$product->id.'/edit'
        );

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => $isNew ? 'Ulasan berhasil dikirim.' : 'Ulasan berhasil diperbarui.',
        ]);
    }
}
