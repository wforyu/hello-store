<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialFollowClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialFollowController extends Controller
{
    public function rules(): JsonResponse
    {
        $enabled = Setting::get('social_follow_enabled', '0') === '1';
        $rules = Setting::get('social_follow_rules', []);

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $enabled,
                'rules' => $rules,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $claims = SocialFollowClaim::forUser($user->id)->get();

        $result = [];
        foreach (SocialFollowClaim::PLATFORMS as $platform) {
            $claim = $claims->where('platform', $platform)->first();
            $result[$platform] = [
                'status' => $claim?->status ?? 'none',
                'claimed_at' => $claim?->created_at?->toISOString(),
                'reviewed_at' => $claim?->reviewed_at?->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function claim(Request $request, string $platform): JsonResponse
    {
        $user = $request->user();

        if (! in_array($platform, SocialFollowClaim::PLATFORMS)) {
            return response()->json([
                'success' => false,
                'message' => 'Platform tidak valid.',
            ], 422);
        }

        $enabled = Setting::get('social_follow_enabled', '0') === '1';
        if (! $enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur Social Follow Rewards belum diaktifkan.',
            ], 422);
        }

        $existing = SocialFollowClaim::forUser($user->id)
            ->where('platform', $platform)
            ->first();

        if ($existing && $existing->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah claim reward ini.',
            ], 422);
        }

        if ($existing && $existing->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Claim Anda sedang dalam review.',
            ], 422);
        }

        $rules = Setting::get('social_follow_rules', []);
        $rule = null;
        foreach ($rules as $r) {
            if ($r['platform'] === $platform) {
                $rule = $r;
                break;
            }
        }

        if (! $rule) {
            return response()->json([
                'success' => false,
                'message' => 'Platform ini belum dikonfigurasi.',
            ], 422);
        }

        if ($existing) {
            $existing->update([
                'status' => 'pending',
                'reward_tier' => $rule['reward_tier'] ?? null,
                'reward_points' => (int) ($rule['reward_points'] ?? 0),
                'admin_notes' => null,
                'reviewed_at' => null,
            ]);
        } else {
            SocialFollowClaim::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'status' => 'pending',
                'reward_tier' => $rule['reward_tier'] ?? null,
                'reward_points' => (int) ($rule['reward_points'] ?? 0),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Claim berhasil diajukan. Menunggu review admin.',
        ]);
    }
}
