<?php

namespace App\Http\Controllers;

use App\Support\IdleTimeout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionController extends Controller
{
    /**
     * Dipanggil frontend idle-timer saat user benar-benar berinteraksi (klik,
     * ketik, geser mouse). Ini yang menyegarkan last activity — bukan
     * polling Livewire dashboard, yang diabaikan middleware.
     */
    public function keepAlive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area' => ['required', 'string', Rule::in(['admin', 'pos'])],
        ]);

        $session = $request->session();
        IdleTimeout::touch($session);

        return response()->json(IdleTimeout::snapshot($session, $validated['area']));
    }
}
