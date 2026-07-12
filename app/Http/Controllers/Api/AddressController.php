<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', auth()->id())
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses,
            'message' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'recipient' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'street' => 'required|string',
            'city' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'notes' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if (! empty($validated['is_default']) && $validated['is_default']) {
            Address::where('user_id', auth()->id())->update(['is_default' => false]);
        }

        $address = Address::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'data' => $address,
            'message' => 'Alamat berhasil ditambahkan.',
        ], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|required|string|max:255',
            'recipient' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'street' => 'sometimes|required|string',
            'city' => 'sometimes|required|string|max:255',
            'province' => 'sometimes|required|string|max:255',
            'postal_code' => 'sometimes|required|string|max:10',
            'notes' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        if (! empty($validated['is_default']) && $validated['is_default']) {
            Address::where('user_id', auth()->id())
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'success' => true,
            'data' => $address->fresh(),
            'message' => 'Alamat berhasil diperbarui.',
        ]);
    }

    public function destroy(Address $address): JsonResponse
    {
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $address->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Alamat berhasil dihapus.',
        ]);
    }
}
