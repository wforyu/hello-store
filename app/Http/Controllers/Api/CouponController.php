<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (! $coupon) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Kupon tidak ditemukan.',
            ], 404);
        }

        if (! $coupon->isValid()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Kupon sudah tidak berlaku atau kedaluwarsa.',
            ], 422);
        }

        if (! $coupon->canUseBy($request->user())) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Anda sudah mencapai batas penggunaan kupon ini.',
            ], 422);
        }

        $subtotal = (float) $validated['subtotal'];

        if ($subtotal < (float) $coupon->min_order) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Minimal pembelian '.number_format($coupon->min_order, 0, ',', '.').' untuk menggunakan kupon ini.',
            ], 422);
        }

        $discountAmount = $coupon->calculateDiscount($subtotal);

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => (float) $coupon->value,
                'discount_amount' => $discountAmount,
                'discount_amount_formatted' => 'Rp'.number_format($discountAmount, 0, ',', '.'),
                'description' => $coupon->description,
            ],
            'message' => 'Kupon berhasil diterapkan.',
        ]);
    }
}
