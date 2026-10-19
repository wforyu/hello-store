<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serialisasi request yang berbagi session yang sama (anti race keranjang POS).
 *
 * Laravel menyimpan session di akhir SETIAP request (termasuk GET seperti
 * /pos/search), dan driver database tidak punya locking: dua request
 * tumpang-tindih membaca snapshot yang sama lalu saling menimpa — pemenangnya
 * yang menulis TERAKHIR. Di POS, kasir yang klik cepat (tambah produk lalu
 * langsung klik produk berikutnya, atau debounce diskon 400 ms yang masih
 * jalan) mengirim request berbarengan, sehingga keranjang di layar tidak
 * sinkron dengan session di database — gejala "produk muncul sebentar lalu
 * hilang, harus refresh dulu baru benar".
 *
 * Kenapa BUKAN `session.block` bawaan Laravel: mekanisme bawaan MELEMPAR
 * exception (LockTimeoutException / QueryException) yang berujung 500 untuk
 * SEMUA request saat kunci tidak bisa diambil — di shared hosting tanpa akses
 * log itu artinya POS mogok total tanpa keterangan. Middleware ini GAGAL-OPEN:
 * kalau kunci gagal dibuat atau giliran ditunggu terlalu lama, request tetap
 * jalan tanpa serialisasi (race memang tidak enak, tapi jauh lebih baik
 * daripada kasir tidak bisa jualan), dan setiap kegagalan dicatat ke
 * laravel.log supaya tetap terdiagnosa.
 */
class SerializeSessionRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // `session.serialize_requests` = tombol utama. Tanpa session (stateless)
        // tidak ada yang bisa berebut, jadi tidak perlu kunci. Catatan: jangan
        // pakai key `session.block` — itu milik mekanisme blocking BAWAAN
        // Laravel (StartSession) yang gagal = 500 keras, lihat config/session.php.
        if (! config('session.serialize_requests', true)) {
            return $this->withLockHeader($next($request), 'disabled');
        }

        if (! $request->hasSession()) {
            return $next($request);
        }

        $lockSeconds = (int) config('session.block_lock_seconds', 30);
        $waitSeconds = (int) config('session.block_wait_seconds', 10);

        // Status kunci ikut dibawa sebagai header `X-Session-Lock` supaya
        // kondisi di produksi bisa diperiksa langsung dari DevTools → Network,
        // tanpa harus membuka log: `acquired` (berseri), `disabled`,
        // `create-failed` (kunci tidak bisa dibuat), `timeout` (antrean
        // terlalu panjang), `error` (gagal tak terduga).
        $status = 'acquired';
        $lock = null;

        try {
            $lock = Cache::store()
                ->lock('session:'.$request->session()->getId(), $lockSeconds)
                // Serah-terima kunci harus rapat: di POS setiap milidetik
                // antrean terasa oleh kasir.
                ->betweenBlockedAttemptsSleepFor(50);
        } catch (Throwable $e) {
            // Driver cache tidak mendukung lock, atau tabel cache_locks hilang.
            Log::error('Session lock gagal dibuat; request lanjut tanpa serialisasi.', [
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);

            $status = 'create-failed';
        }

        if ($lock) {
            try {
                $lock->block($waitSeconds);
            } catch (LockTimeoutException) {
                // Antrean terlalu panjang (request lain sedang sangat lambat).
                // Kasir tidak boleh menunggu berkepanjangan — lanjut tanpa kunci.
                Log::warning('Session lock tidak dapat dalam '.$waitSeconds.' detik; request lanjut tanpa serialisasi.', [
                    'path' => $request->path(),
                ]);

                $status = 'timeout';
                $lock = null;
            } catch (Throwable $e) {
                Log::error('Session lock gagal; request lanjut tanpa serialisasi.', [
                    'path' => $request->path(),
                    'error' => $e->getMessage(),
                ]);

                $status = 'error';
                $lock = null;
            }
        }

        try {
            return $this->withLockHeader($next($request), $status);
        } finally {
            $lock?->release();
        }
    }

    /** Tandai respons dengan status kunci session untuk diagnosa cepat. */
    protected function withLockHeader(Response $response, string $status): Response
    {
        $response->headers->set('X-Session-Lock', $status);

        return $response;
    }
}
