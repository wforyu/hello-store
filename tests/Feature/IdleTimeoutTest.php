<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IdleTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class IdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private const TIMEOUT_MINUTES = 30;

    private function minutesAgo(int $minutes): int
    {
        return time() - ($minutes * 60);
    }

    // --- konfigurasi ------------------------------------------------------

    public function test_default_adalah_30_menit_untuk_admin_dan_pos(): void
    {
        $this->assertSame(30, IdleTimeout::timeoutMinutes('admin'));
        $this->assertSame(30, IdleTimeout::timeoutMinutes('pos'));
    }

    public function test_timeout_bisa_dimatikan_dengan_nol(): void
    {
        config(['session.idle_timeout.pos' => 0]);

        $this->assertFalse(IdleTimeout::isEnabledFor('pos'));
    }

    public function test_area_terdaftar_di_config_tidak_bisa_kena_timeout(): void
    {
        // Mencegah salah pasang middleware dengan area yang tidak ada di config
        // (mis. 'kasir'), yang akan membuat session tidak pernah expired.
        $this->assertFalse(IdleTimeout::isEnabledFor('storefront'));
    }

    // --- perhitungan sisa waktu -------------------------------------------

    public function test_sesi_baru_dianggap_aktif_mulai_sekarang(): void
    {
        $now = time();
        $session = Session::driver();

        $this->assertSame($now, IdleTimeout::lastActivity($session, $now));
        $this->assertFalse(IdleTimeout::isExpired($session, 'admin', $now));
    }

    public function test_remaining_menurun_seiring_waktu(): void
    {
        $now = time();
        $session = Session::driver();
        $timeoutSeconds = self::TIMEOUT_MINUTES * 60;
        IdleTimeout::touch($session, $now - 600);

        // 600 detik lalu, dari total 1800 => sisa 1200 detik.
        $this->assertSame($timeoutSeconds - 600, IdleTimeout::remainingSeconds($session, 'admin', $now));
    }

    public function test_expired_tepat_setelah_30_menit(): void
    {
        $now = time();
        $session = Session::driver();

        IdleTimeout::touch($session, $now - (self::TIMEOUT_MINUTES * 60) + 5);
        $this->assertFalse(IdleTimeout::isExpired($session, 'admin', $now), 'masih dalam batas');

        IdleTimeout::touch($session, $now - (self::TIMEOUT_MINUTES * 60));
        $this->assertTrue(IdleTimeout::isExpired($session, 'admin', $now), 'tepat di batas = expired');
    }

    public function test_jendela_peringatan_adalah_60_detik_terakhir(): void
    {
        $now = time();
        $session = Session::driver();
        $total = self::TIMEOUT_MINUTES * 60;

        IdleTimeout::touch($session, $now - ($total - 120));
        $this->assertFalse(IdleTimeout::inWarningWindow($session, 'admin', $now));

        IdleTimeout::touch($session, $now - ($total - 60));
        $this->assertTrue(IdleTimeout::inWarningWindow($session, 'admin', $now));

        IdleTimeout::touch($session, $now - ($total - 1));
        $this->assertTrue(IdleTimeout::inWarningWindow($session, 'admin', $now), 'sisa 1 detik');

        IdleTimeout::touch($session, $now - ($total + 1));
        $this->assertFalse(IdleTimeout::inWarningWindow($session, 'admin', $now), 'sudah lewat = bukan peringatan');
    }

    public function test_timeout_dimatikan_tidak_pernah_expired(): void
    {
        $now = time();
        $session = Session::driver();
        config(['session.idle_timeout.admin' => 0]);
        IdleTimeout::touch($session, $now - (86400 * 30));

        $this->assertFalse(IdleTimeout::isExpired($session, 'admin', $now));
        $this->assertFalse(IdleTimeout::inWarningWindow($session, 'admin', $now));
    }

    // --- pertahanan terhadap polling --------------------------------------

    public function test_request_livewire_polling_tidak_menyegarkan_aktivitas(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $stale = $this->minutesAgo(self::TIMEOUT_MINUTES - 1); // 1 menit sebelum expired

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $stale])
            ->withHeaders(['X-Livewire' => 'true'])
            ->get('/pos');

        $response->assertOk();

        // Kalau polling ikut menyegarkan, nilai ini jadi time() dan session
        // tidak akan pernah expired selamanya.
        $this->assertSame(
            $stale,
            Session::get(IdleTimeout::SESSION_KEY),
            'Polling Livewire tidak boleh menyentuh last activity, kalau tidak auto-logout tidak akan pernah terjadi.'
        );
    }

    public function test_request_biasa_menyegarkan_aktivitas(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $stale = $this->minutesAgo(10);

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $stale])
            ->get('/pos');

        $response->assertOk();
        $this->assertGreaterThan(time() - 5, Session::get(IdleTimeout::SESSION_KEY));
    }

    public function test_aktivitas_tidak_memperpanjang_di_jendela_peringatan(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $total = self::TIMEOUT_MINUTES * 60;
        $inWarning = time() - ($total - 30); // sisa 30 detik

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $inWarning])
            ->get('/pos');

        $response->assertOk();
        $this->assertSame(
            $inWarning,
            Session::get(IdleTimeout::SESSION_KEY),
            'Di jendela peringatan last activity tidak boleh di-refresh, supaya session benar-benar berakhir.'
        );
    }

    // --- tegakan di area sensitif -----------------------------------------
    public function test_pos_kasir_expired_diarahkan_ke_login(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(self::TIMEOUT_MINUTES + 1)])
            ->get('/pos');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_expired_diarahkan_ke_login_filament(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(self::TIMEOUT_MINUTES + 1)])
            ->get('/admin');

        $response->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_pesan_alasan_ditampilkan_di_halaman_login(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(self::TIMEOUT_MINUTES + 1)])
            ->get('/pos')
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->get('/login')->assertSee('Sesi Anda berakhir');
    }

    public function test_ajax_yang_expired_dibalas_401_bukan_redirect(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(self::TIMEOUT_MINUTES + 1)])
            ->getJson('/pos/search?q=kaos');

        $response->assertStatus(401);
    }

    // --- yang tidak boleh terpengaruh ------------------------------------

    public function test_customer_storefront_tidak_terkena_timeout(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(60 * 24)])
            ->get('/');

        $response->assertOk();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_tamu_tidak_perlu_login_ke_panel_admin(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_pos_yang_aktif_tidak_terlogout(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(5)])
            ->get('/pos');

        $response->assertOk();
        $this->assertAuthenticatedAs($cashier);
    }

    // --- keep-alive --------------------------------------------------------

    public function test_keep_alive_memperpanjang_sesi(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)
            ->postJson('/session/keep-alive', ['area' => 'pos']);

        $response->assertOk();
        $this->assertSame(self::TIMEOUT_MINUTES * 60, $response->json('remaining'));
        $this->assertSame('pos', $response->json('area'));
    }

    public function test_keep_alive_masih_boleh_dipakai_saat_sudah_expired(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)
            ->withSession([IdleTimeout::SESSION_KEY => $this->minutesAgo(self::TIMEOUT_MINUTES + 5)])
            ->postJson('/session/keep-alive', ['area' => 'pos']);

        $response->assertOk();
        $this->assertSame(self::TIMEOUT_MINUTES * 60, $response->json('remaining'));
    }

    public function test_keep_alive_menolak_area_tidak_dikenal(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->postJson('/session/keep-alive', ['area' => 'nope'])
            ->assertStatus(422);
    }

    public function test_keep_alive_membutuhkan_login(): void
    {
        $this->postJson('/session/keep-alive', ['area' => 'pos'])->assertStatus(401);
    }

    // --- regresi shouldRenderJsonWhen ---------------------------------------

    /**
     * Callback shouldRenderJsonWhen mengganti (bukan menambah) expectsJson().
     * Kalau hanya 'api/*', session expired di endpoint AJAX web membalas 302
     * HTML dan response.json() di frontend jadi throw.
     */
    public function test_endpoint_ajax_web_tetap_json_kala_session_habis(): void
    {
        // /orders ada di group 'auth' (bukan api/*). Sebelum fix, request AJAX
        // ke sini dibalas 302 HTML sehingga response.json() di frontend throw.
        $this->getJson('/orders')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json');

        // Route publik tetap JSON normal.
        $this->getJson('/cart/count')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_validasi_gagal_di_endpoint_ajax_web_menghasilkan_422(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $this->actingAs($cashier)
            ->postJson('/pos/add', [])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json');
    }

    // --- render frontend ---------------------------------------------------

    public function test_partial_timer_ter_render_di_pos(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);

        $response = $this->actingAs($cashier)->get('/pos');

        $response->assertOk();
        $response->assertSee('idle-timer-root', false);
        $response->assertSee(route('session.keep-alive'), false);
        $response->assertSee('Tetap masuk', false);
    }
}
