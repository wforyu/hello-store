<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockHistory;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the behaviour of the session-based web POS after the checkout logic
 * moved out of the controller and into PosService.
 */
class PosWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier']);
        $this->actingAs($this->cashier);

        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '11']);
    }

    protected function makeProduct(string $name, int $stock, int $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'web-pos-kategori'], ['name' => 'Web POS Kategori']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price' => $price,
            'stock' => $stock,
            'sku' => 'WEB-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }

    public function test_halaman_pos_bisa_diakses_kasir(): void
    {
        $this->get('/pos')->assertOk()->assertSee('POS', false);
    }

    public function test_halaman_pos_menolak_customer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        $this->get('/pos')->assertForbidden();
    }

    public function test_halaman_pos_pakai_pencarian_pos_bukan_storefront(): void
    {
        $response = $this->get('/pos')->assertOk();

        $response->assertSee('Cari produk di POS', false);
        $response->assertSee('function posNavSearch', false);
        $response->assertSee('pos:add-product', false);
        $response->assertDontSee('action="'.url('/products').'"', false);
    }

    /**
     * Semua request yang mengubah session cart (tambah, update qty/diskon, hapus,
     * hold, recall, delete hold, scan, checkout) wajib lewat enqueueCartRequest.
     *
     * Tanpa antrean, dua request tumpang-tindih membaca session yang sama lalu
     * saling menimpa (last-writer-wins dengan snapshot basi) — gejala "produk
     * muncul sebentar lalu hilang, harus refresh dulu". Lapis utamanya ada di
     * server (middleware SerializeSessionRequests); antrean client ini jaring
     * kedua yang tidak bergantung pada kondisi hosting.
     */
    public function test_mutasi_cart_pos_lewat_antrean_request(): void
    {
        $html = $this->get('/pos')->assertOk()->getContent();

        $this->assertStringContainsString('enqueueCartRequest', $html);

        $this->assertGreaterThanOrEqual(
            8,
            substr_count($html, 'this.enqueueCartRequest('),
            'Semua mutasi cart POS harus lewat antrean enqueueCartRequest, bukan posPost/fetch langsung.'
        );
    }

    public function test_halaman_storefront_tetap_pakai_pencarian_produk(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Cari produk...', false);
        $response->assertSee('action="'.url('/products').'"', false);
    }

    public function test_tambah_keranjang_dibatasi_stok(): void
    {
        $product = $this->makeProduct('Sisa Stok', 2, 10000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 10])
            ->assertOk()
            ->assertJsonPath('cart.0.quantity', 2);
    }

    public function test_tambah_keranjang_menolak_produk_stok_nol(): void
    {
        $product = $this->makeProduct('Habis', 0, 10000);

        $this->postJson('/pos/add', ['product_id' => $product->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Stok produk habis!');
    }

    public function test_checkout_membuat_order_serta_mengurangi_stok(): void
    {
        $product = $this->makeProduct('Kopi Botol', 10, 15000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $response = $this->postJson('/pos/checkout', [
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'customer_name' => 'Rina',
            'amount_paid' => 50000,
        ])->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('change', 20000);

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, Order::count());
        $this->assertStringStartsWith('Dine-in - Rina', Order::first()->notes);
    }

    public function test_checkout_mengosongkan_keranjang(): void
    {
        $product = $this->makeProduct('Karet', 10, 5000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'takeaway',
        ])->assertOk();

        $this->assertTrue(collect(session('pos_cart', []))->isEmpty());
    }

    public function test_checkout_menolak_keranjang_kosong(): void
    {
        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ])->assertStatus(422)->assertJsonPath('error', 'Keranjang kosong!');
    }

    public function test_checkout_menolak_stok_tidak_cukup_dan_tidak_membuat_order(): void
    {
        $product = $this->makeProduct('Tisu', 1, 7000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        // Another terminal drained the last unit in the meantime.
        $product->update(['stock' => 0]);

        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ])->assertStatus(422)->assertJsonPath('error', fn ($m) => str_contains((string) $m, 'tidak mencukupi'));

        $this->assertSame(0, Order::count());
        $this->assertTrue(collect(session('pos_cart', []))->isNotEmpty());
    }

    public function test_checkout_tunai_menolak_uang_kurang(): void
    {
        $product = $this->makeProduct('Pena', 10, 12000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson('/pos/checkout', [
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'amount_paid' => 1000,
        ])->assertStatus(422)->assertJsonPath('error', 'Jumlah dibayar kurang dari total!');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_menempelkan_order_ke_shift_aktif(): void
    {
        $shift = Shift::create([
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_balance' => 50000,
        ]);

        $product = $this->makeProduct('Kabel', 10, 9000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ])->assertOk();

        $this->assertSame($shift->id, Order::first()->shift_id);
    }

    public function test_ppn_dihitung_dan_disimpan_di_notes(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '1']);

        $product = $this->makeProduct('Sapu', 10, 50000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
            'ppn' => true,
        ])->assertOk()->assertJsonPath('ppn', 5500);

        $this->assertStringContainsString('PPN 11%: Rp 5.500', Order::first()->notes);
    }

    public function test_riwayat_stok_pos_mencatat_nilai_sebelum_dan_sesudah(): void
    {
        $product = $this->makeProduct('Gorden', 12, 30000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 3])->assertOk();
        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ])->assertOk();

        $history = StockHistory::where('product_id', $product->id)->where('type', 'pos')->firstOrFail();

        $this->assertSame(-3, $history->quantity_change);
        $this->assertSame(12, $history->stock_before);
        $this->assertSame(9, $history->stock_after);
    }

    public function test_diskon_baris_persen_dipakai_saat_checkout(): void
    {
        $product = $this->makeProduct('Jas Hujan', 10, 50000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        // Payload diskon sengaja tanpa `quantity`: sync diskon tidak boleh
        // menimpa qty yang sudah diatur kasir lewat tombol +/−.
        $this->postJson('/pos/update', [
            'product_id' => $product->id,
            'discount' => 10,
            'discount_type' => 'percent',
        ])->assertOk()
            ->assertJsonPath('cart.0.quantity', 2)
            ->assertJsonPath('cart.0.discount', 10)
            ->assertJsonPath('cart.0.discount_type', 'percent');

        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ])->assertOk();

        // 2 x 50.000 = 100.000, diskon 10% = 10.000
        $this->assertSame('90000.00', Order::first()->total);
        $this->assertSame('90000.00', Order::first()->items()->first()->subtotal);
    }

    public function test_diskon_baris_nominal_dibatasi_total_baris(): void
    {
        $product = $this->makeProduct('Payung', 10, 20000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/pos/update', [
            'product_id' => $product->id,
            'discount' => 999999,
            'discount_type' => 'nominal',
        ])->assertOk()->assertJsonPath('cart.0.discount', 999999);

        $this->postJson('/pos/checkout', [
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'amount_paid' => 0,
        ])->assertOk();

        // Diskon tidak boleh melebihi harga baris, jadi total mentok di 0.
        $this->assertSame('0.00', Order::first()->total);
    }

    public function test_diskon_baris_tunai_divalidasi_terhadap_total_setelah_diskon(): void
    {
        $product = $this->makeProduct('Tas', 10, 15000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/pos/update', [
            'product_id' => $product->id,
            'discount' => 5000,
            'discount_type' => 'nominal',
        ])->assertOk();

        // 15.000 - 5.000 = 10.000; bayar 10.000 harus diterima.
        $this->postJson('/pos/checkout', [
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'amount_paid' => 10000,
        ])->assertOk()->assertJsonPath('change', 0);
    }

    public function test_payload_keranjang_selalu_list_setelah_item_dihapus(): void
    {
        $first = $this->makeProduct('Item Pertama', 5, 10000);
        $second = $this->makeProduct('Item Kedua', 5, 20000);

        $this->postJson('/pos/add', ['product_id' => $first->id])->assertOk();
        $this->postJson('/pos/add', ['product_id' => $second->id])->assertOk();
        $this->postJson('/pos/remove', ['product_id' => $first->id])->assertOk();

        $response = $this->postJson('/pos/update', [
            'product_id' => $second->id,
            'discount' => 2000,
        ])->assertOk();

        $cart = $response->json('cart');

        // Kalau payload jadi object, x-for di POS merender nol baris dan seluruh
        // item kelihatan hilang.
        $this->assertTrue(array_is_list($cart));
        $this->assertSame($second->id, $cart[0]['product_id']);
        $this->assertSame(2000, (int) $cart[0]['discount']);
    }

    public function test_hold_dan_recall_mengembalikan_keranjang(): void
    {
        $product = $this->makeProduct('Sapu Lantai', 10, 25000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->postJson('/pos/hold', ['name' => 'Meja 3'])->assertOk();

        $this->assertTrue(collect(session('pos_cart', []))->isEmpty());

        $this->getJson('/pos/hold/1')->assertOk()->assertJsonPath('cart.0.product_id', $product->id);
        $this->assertTrue(collect(session('pos_holds', []))->isEmpty());
    }

    public function test_scan_barcode_menambahkan_produk(): void
    {
        $product = $this->makeProduct('Kabel Data', 10, 40000);

        $this->postJson('/pos/scan', ['barcode' => $product->sku])
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('cart.0.product_id', $product->id);
    }

    public function test_scan_barcode_melapor_produk_tidak_ditemukan(): void
    {
        $this->postJson('/pos/scan', ['barcode' => 'SKU-TIDAK-ADA'])
            ->assertOk()
            ->assertJsonPath('found', false);
    }

    public function test_riwayat_transaksi_hari_ini(): void
    {
        $product = $this->makeProduct('Kaos', 10, 60000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'takeaway',
            'customer_name' => 'Andi',
        ])->assertOk();

        $this->getJson('/pos/history')
            ->assertOk()
            ->assertJsonPath('0.customer_name', 'Andi')
            ->assertJsonPath('0.total', 60000);
    }

    public function test_buka_dan_tutup_shift(): void
    {
        $this->post('/pos/shift/open', ['opening_balance' => 100000])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('shifts', [
            'user_id' => $this->cashier->id,
            'opening_balance' => 100000,
        ]);

        $this->post('/pos/shift/close', ['closing_balance' => 100000])
            ->assertRedirect(route('pos.index'));

        $this->assertNotNull(Shift::first()->closed_at);
    }

    public function test_shift_tidak_bisa_dibuka_ganda(): void
    {
        Shift::create(['user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_balance' => 0]);

        $this->post('/pos/shift/open', ['opening_balance' => 50000]);

        $this->assertSame(1, Shift::count());
    }

    /**
     * Keluhan "tambah produk di POS harus refresh dulu".
     *
     * Servernya sendiri sudah benar (`POST /pos/add` mengembalikan cart terbaru),
     * jadi yang dijaga di sini adalah sisi layarnya: baris ditampilkan lebih
     * dulu (optimistis), lalu disinkronkan. Tidak boleh ada reload halaman,
     * karena reload membuat setiap penambahan terasa "harus refresh" dan diskon
     * baris lain yang belum tersimpan ikut hilang.
     */
    public function test_halaman_pos_memperbarui_keranjang_tanpa_memuat_ulang_halaman(): void
    {
        $html = $this->get('/pos')->assertOk()->getContent();

        // Satu-satunya reload yang boleh ada di halaman ini milik auto-logout idle
        // (`partials/idle-timer`). Kalau jumlahnya nambah, berarti ada alur POS
        // yang kembali "harus refresh dulu".
        $this->assertSame(1, substr_count($html, 'window.location.reload'), 'Hanya idle-timer yang boleh memuat ulang halaman POS.');

        $this->assertStringContainsString('this.cart.push(this.productToCartItem(product))', $html);

        // Semua panggilan POS lewat dua pintu yang selalu minta JSON dan tidak
        // pernah melempar — kegagalan harus muncul di layar kasir, bukan hilang
        // sebagai unhandled rejection.
        $this->assertStringContainsString('posPost(', $html);
        $this->assertStringContainsString('posGet(', $html);
        $this->assertStringContainsString("'Accept': 'application/json'", $html);
    }

    /**
     * Diskon baris yang belum terkirim (masih menunggu debounce) tidak boleh
     * hilang ketika cart disegarkan dari server — mis. kasir mengetik diskon
     * lalu langsung menekan tombol +.
     */
    public function test_diskon_baris_yang_belum_tersimpan_dipertahankan_saat_cart_disinkron(): void
    {
        $html = $this->get('/pos')->assertOk()->getContent();

        $this->assertStringContainsString('if (old && old._dirty)', $html);
        $this->assertStringContainsString('discountText: discount > 0', $html);

        // Input diskon terikat ke discountText; payload server cuma punya
        // `discount`, jadi konversinya wajib ada di satu tempat.
        $this->assertStringContainsString('hydrateCartItem', $html);
    }

    /** Menambah produk yang sudah ada di keranjang menaikkan qty, bukan baris baru. */
    public function test_tambah_produk_yang_sudah_ada_menambah_kuantitas(): void
    {
        $product = $this->makeProduct('Teh Kotak', 5, 6000);

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonCount(1, 'cart')
            ->assertJsonPath('cart.0.quantity', 2);
    }

    /** Balasan `update` tetap membawa harga + stok supaya layar bisa disegarkan. */
    public function test_balasan_update_membawa_harga_dan_stok_terbaru(): void
    {
        $product = $this->makeProduct('Sirup', 7, 12000);

        $this->postJson('/pos/add', ['product_id' => $product->id])->assertOk();

        $this->postJson('/pos/update', ['product_id' => $product->id, 'quantity' => 3])
            ->assertOk()
            ->assertJsonPath('cart.0.quantity', 3)
            ->assertJsonPath('cart.0.stock', 7)
            ->assertJsonPath('cart.0.price', 12000);
    }

    /**
     * Server POS wajib membawa pesan exception aslinya saat 500.
     *
     * Di InfinityFree tidak ada SSH dan tidak ada akses log, jadi "Server
     * Error" polos membuat setiap kegagalan harus ditebak. Endpoint /pos/*
     * ber-Accept-JSON dari kasir yang sudah login kini membalas 500 JSON
     * berisi `ExceptionClass: pesan` — banner merah POS menampilkannya.
     */
    public function test_error_500_pos_membawa_pesan_asli_ke_layar_kasir(): void
    {
        $product = $this->makeProduct('Kopi Sachet', 10, 3000);

        $this->app->bind(PosService::class, function () {
            throw new \RuntimeException('Simulasi produksi: class PosService tidak ditemukan');
        });

        $this->postJson('/pos/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(500)
            ->assertJsonPath(
                'error',
                'RuntimeException: Simulasi produksi: class PosService tidak ditemukan'
            );
    }

    /** 4xx (validasi/auth) tidak boleh ikut terseret ke envelope 500 di atas. */
    public function test_validasi_pos_tetap_membalas_422_bukan_envelope_500(): void
    {
        $this->postJson('/pos/add', ['quantity' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }
}
