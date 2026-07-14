<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
}
