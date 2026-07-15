<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        $request->validate([
            'address_id' => 'required|exists:addresses,id',
        ]);

        $address = auth()->user()->addresses()->findOrFail($request->address_id);

        $cart = Cart::where('user_id', auth()->id())->first();
        if (! $cart) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Keranjang kosong.',
            ], 422);
        }

        $cart->load(['items.product', 'items.variant']);
        $totalWeight = 0;
        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }
            $weight = (float) ($product->weight ?? 0);
            if ($item->product_variant_id && $item->variant) {
                $weight = (float) ($item->variant->weight ?? $weight);
            }
            $totalWeight += $weight * $item->quantity;
        }
        $totalWeightGrams = max((int) ($totalWeight * 1000), 1000);

        $shippingService = new ShippingService;
        $city = $address->city ?? '';
        $rates = $shippingService->getRates($city, $totalWeightGrams);

        $formattedRates = [];
        foreach ($rates as $courier) {
            $services = [];
            foreach ($courier['rates'] ?? [] as $rate) {
                $services[] = [
                    'service' => $rate['service'],
                    'description' => $rate['description'],
                    'cost' => $rate['cost'],
                    'cost_formatted' => 'Rp'.number_format($rate['cost'], 0, ',', '.'),
                    'etd' => $rate['etd'],
                ];
            }
            $formattedRates[] = [
                'code' => $courier['code'],
                'name' => $courier['name'],
                'services' => $services,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'rates' => $formattedRates,
                'weight_grams' => $totalWeightGrams,
                'city' => $city,
            ],
        ]);
    }
}
