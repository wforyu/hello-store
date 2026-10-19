<?php

namespace Tests\Feature;

use App\Http\Middleware\SerializeSessionRequests;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Guard anti-regresi untuk serialisasi request per session (config
 * `session.block` + middleware SerializeSessionRequests).
 *
 * Tanpa serialisasi, dua request POS yang tumpang-tindih saling menimpa
 * session di database (last-writer-wins dengan snapshot basi) — persis
 * penyebab "produk muncul sebentar lalu hilang, harus refresh dulu".
 *
 * Dua hal yang dijaga di sini:
 * 1. Serialisasi benar-benar aktif dan middlewarenya terpasang.
 * 2. Gagal-OPEN: kalau kunci tidak bisa dibuat (driver tanpa lock, tabel
 *    cache_locks hilang), request TETAP jalan dan kegagalannya di-log —
 *    dianggapan penting karena di shared hosting tanpa akses log, versi
 *    keras (lempar exception → 500) membuat POS mogok total tanpa keterangan.
 */
class SessionBlockingTest extends TestCase
{
    /**
     * Key yang benar adalah `session.serialize_requests` (middleware fail-open).
     * `session.block` framework bawaan HARUS tetap false — mengaktifkannya
     * terbukti bikin 500 keras di /pos/add & /pos/remove di produksi.
     */
    public function test_serialisasi_aktif_dan_mekanisme_bawaan_tetap_mati(): void
    {
        $this->assertTrue(config('session.serialize_requests'));
        $this->assertFalse(config('session.block'));
    }

    public function test_middleware_serialisasi_terpasang_di_web_group(): void
    {
        $web = app('router')->getMiddlewareGroups()['web'];

        $this->assertContains(SerializeSessionRequests::class, $web);
    }

    public function test_cache_store_default_mendukung_session_lock(): void
    {
        $store = Cache::store()->getStore();

        $this->assertInstanceOf(LockProvider::class, $store);
    }

    public function test_kunci_session_id_bersifat_eksklusif_per_session(): void
    {
        $cache = Cache::store();

        // Request pertama mengunci session ID-nya.
        $first = $cache->lock('session:pos-race-test', 30);
        $this->assertTrue($first->get());

        // Request kedua dengan session yang SAMA harus menunggu (kuncinya diambil).
        $second = $cache->lock('session:pos-race-test', 30);
        $this->assertFalse($second->get());

        // Request dengan session BERBEDA tidak boleh ikut terblokir.
        $other = $cache->lock('session:lain-test', 30);
        $this->assertTrue($other->get());

        // Setelah request pertama selesai, giliran berikutnya bebas mengunci.
        $first->release();
        $this->assertTrue($cache->lock('session:pos-race-test', 30)->get());
    }

    public function test_lock_gagal_tidak_memblokir_request(): void
    {
        Log::spy();

        // Simulasi kondisi hosting yang pernah terjadi: pembuatan kunci
        // melempar exception (tabel cache_locks hilang, driver cache rusak,
        // dll). Middleware harus menelannya, mencatatnya ke log, lalu
        // meneruskan request — BUKAN mematikan POS dengan 500.
        Cache::shouldReceive('store')
            ->andThrow(new RuntimeException('Table cache_locks does not exist.'));

        $this->get('/cart/count')->assertOk();

        Log::shouldHaveReceived('error');
    }

    public function test_block_dimatikan_request_berjalan_tanpa_kunci(): void
    {
        // Dengan `session.serialize_requests = false` middleware tidak boleh
        // menyentuh cache sama sekali (kill switch untuk debugging darurat).
        config(['session.serialize_requests' => false]);

        Cache::shouldReceive('store')->never();

        $this->get('/cart/count')->assertOk();
    }
}
