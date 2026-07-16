<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'api_url' => Setting::get('mobile_api_url', ''),
                'store_name' => Setting::get('store_name', 'Hello Store'),
            ],
        ]);
    }
}
