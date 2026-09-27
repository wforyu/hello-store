<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Sumber tunggal untuk aturan idle session timeout.
 *
 * Dipakai oleh App\Http\Middleware\EnforceIdleLogout (menegakkan) dan
 * resources/views/partials/idle-timer.blade.php (menampilkan countdown),
 * supaya keduanya tidak bisa berbeda expire.
 *
 * Semua perhitungan pakai time() (epoch detik) yang bebas timezone, bukan
 * now(). Ini sengaja: perbandingan offset detik tidak boleh dipengaruhi
 * konfigurasi timezone app atau database.
 */
class IdleTimeout
{
    public const SESSION_KEY = 'idle_last_activity';

    /**
     * Timeout dalam MENIT untuk sebuah area. 0 berarti dimatikan.
     */
    public static function timeoutMinutes(string $area): int
    {
        if (! config('session.idle_timeout.enabled')) {
            return 0;
        }

        $configured = config("session.idle_timeout.{$area}");

        return is_numeric($configured) ? (int) $configured : 0;
    }

    public static function warningSeconds(): int
    {
        $configured = config('session.idle_timeout.warning');

        return is_numeric($configured) ? (int) $configured : 60;
    }

    public static function isEnabledFor(string $area): bool
    {
        return self::timeoutMinutes($area) > 0;
    }

    public static function lastActivity(Session $session, ?int $now = null): int
    {
        $now ??= time();
        $stored = (int) $session->get(self::SESSION_KEY, 0);

        // Sesi yang belum pernah disentuh (mis. baru login) dianggap aktif
        // mulai sekarang, supaya tidak langsung kena timeout.
        if ($stored <= 0) {
            $session->put(self::SESSION_KEY, $now);

            return $now;
        }

        return $stored;
    }

    public static function touch(Session $session, ?int $now = null): void
    {
        $session->put(self::SESSION_KEY, $now ?? time());
    }

    /**
     * Sisa-detik sebelum session dianggap kedaluwarsa. Negatif = sudah lewat.
     */
    public static function remainingSeconds(Session $session, string $area, ?int $now = null): int
    {
        $timeout = self::timeoutMinutes($area) * 60;

        if ($timeout <= 0) {
            return PHP_INT_MAX;
        }

        $last = self::lastActivity($session, $now);

        return ($last + $timeout) - ($now ?? time());
    }

    public static function isExpired(Session $session, string $area, ?int $now = null): bool
    {
        return self::remainingSeconds($session, $area, $now) <= 0;
    }

    /**
     * Ada di dalam jendela peringatan? Frontend menampilkan modal countdown
     * selama ini, dan middleware SENGAJA tidak menyentuh last activity supaya
     * session benar-benar berakhir kalau user tidak merespons.
     */
    public static function inWarningWindow(Session $session, string $area, ?int $now = null): bool
    {
        $remaining = self::remainingSeconds($session, $area, $now);

        if ($remaining === PHP_INT_MAX) {
            return false;
        }

        return $remaining > 0 && $remaining <= self::warningSeconds();
    }

    /**
     * @return array{timeout:int,remaining:int,warning:int,area:string,enabled:bool}
     */
    public static function snapshot(Session $session, string $area, ?int $now = null): array
    {
        $now ??= time();
        $remaining = self::remainingSeconds($session, $area, $now);

        return [
            'area' => $area,
            'enabled' => $remaining !== PHP_INT_MAX,
            'timeout' => self::timeoutMinutes($area) * 60,
            'remaining' => $remaining === PHP_INT_MAX ? null : $remaining,
            'warning' => self::warningSeconds(),
        ];
    }
}
