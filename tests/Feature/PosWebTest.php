<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockHistory;
use App\Models\User;
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
}
