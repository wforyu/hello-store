<?php

namespace App\Http\Middleware;

use App\Support\IdleTimeout;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan idle session timeout untuk area yang sensitif.
 *
 * Dipasang di:
 *  - panel Filament  -> EnforceIdleLogout:admin
 *  - group route POS -> EnforceIdleLogout:pos
 *
 * CATATAN kenapa SESSION_LIFETIME tidak bisa dipakai untuk ini: Laravel 13
 * Illuminate\Session\Middleware\AuthenticateSession hanya memvalidasi password
 * hash, tidak punya cek idle. Lihat config/session.php.
 *
 * Request yang dianggap "background" (polling Livewire dari dashboard widget)
 * sengaja TIDAK menyegarkan last activity. Kalau tidak, 8 widget yang poll
 * setiap 5 detik akan membuat session tidak pernah idle dan timeout jadi tidak
 * pernah terjadi. Aktivitas user yang sebenarnya ditangkap oleh ping dari
 * frontend (resources/views/partials/idle-timer.blade.php).
 */
class EnforceIdleLogout
{
    public const MESSAGE = 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan login kembali.';

    public function handle(Request $request, Closure $next, string $area = 'admin'): Response
    {
        if (! $request->hasSession() || ! $request->user()) {
            return $next($request);
        }

        if (! IdleTimeout::isEnabledFor($area)) {
            return $next($request);
        }

        $session = $request->session();

        if (IdleTimeout::isExpired($session, $area)) {
            return $this->expire($request, $area);
        }

        // Di dalam jendela peringatan: jangan sentuh last activity, supaya
        // countdown benar-benar berakhir ke logout kalau user tidak merespons.
        if (! IdleTimeout::inWarningWindow($session, $area) && ! $this->isBackgroundRequest($request)) {
            IdleTimeout::touch($session);
        }

        return $next($request);
    }

    /**
     * Polling / AJAX background. POS tidak termasuk: semua fetch di POS
     * dipicu oleh interaksi kasir (search, add, update, checkout), jadi
     * deserve dihitung sebagai aktivitas.
     */
    private function isBackgroundRequest(Request $request): bool
    {
        if ($request->is('api/*')) {
            return true;
        }

        return $request->hasHeader('X-Livewire') || $request->is('livewire/*');
    }

    private function expire(Request $request, string $area): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], 401);
        }

        return redirect()
            ->to($this->loginUrl($area))
            ->with('status', self::MESSAGE);
    }

    private function loginUrl(string $area): string
    {
        if ($area === 'admin' && class_exists(Filament::class)) {
            return Filament::getLoginUrl();
        }

        return route('login');
    }
}
