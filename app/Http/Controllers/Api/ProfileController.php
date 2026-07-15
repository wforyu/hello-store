<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'points' => $user->points,
                'segment' => $user->segment,
                'total_spent' => (float) $user->total_spent,
                'created_at' => $user->created_at,
            ],
            'message' => null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $data = $request->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'points' => $user->points,
            ],
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }
}
