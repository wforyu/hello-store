<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function ppn(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ppn_enabled' => Setting::get('ppn_enabled', '0') === '1',
                'ppn_percentage' => (int) Setting::get('ppn_percentage', 11),
            ],
        ]);
    }

    public function memberTiers(): JsonResponse
    {
        $thresholds = User::getSegmentThresholds();

        return response()->json([
            'success' => true,
            'data' => [
                'tiers' => [
                    'bronze' => [
                        'label' => 'Bronze',
                        'min_spend' => $thresholds['bronze'],
                        'points_multiplier' => 1.0,
                        'discount_percent' => 0,
                        'color' => '#D97706',
                    ],
                    'silver' => [
                        'label' => 'Silver',
                        'min_spend' => $thresholds['silver'],
                        'points_multiplier' => 1.2,
                        'discount_percent' => 5,
                        'color' => '#9CA3AF',
                    ],
                    'gold' => [
                        'label' => 'Gold',
                        'min_spend' => $thresholds['gold'],
                        'points_multiplier' => 1.5,
                        'discount_percent' => 10,
                        'color' => '#F59E0B',
                    ],
                    'platinum' => [
                        'label' => 'Platinum',
                        'min_spend' => $thresholds['platinum'],
                        'points_multiplier' => 2.0,
                        'discount_percent' => 15,
                        'color' => '#10B981',
                    ],
                    'diamond' => [
                        'label' => 'Diamond',
                        'min_spend' => $thresholds['diamond'],
                        'points_multiplier' => 2.5,
                        'discount_percent' => 20,
                        'color' => '#8B5CF6',
                    ],
                ],
                'points_rate' => (float) Setting::get('points_rate', '10'),
                'points_max_redeem' => (float) Setting::get('points_max_redeem', '50'),
            ],
        ]);
    }
}
