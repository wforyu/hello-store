<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'cashier']);
        Sanctum::actingAs($this->cashier);

        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '11']);
    }

    protected function makeProduct(string $name, int $stock, int $price, bool $active = true): Product
    {
        $category = Category::firstOrCreate(['slug' => 'api-pos-kategori'], ['name' => 'API POS Kategori']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price' => $price,
            'stock' => $stock,
            'sku' => 'API-'.Str::upper(Str::random(6)),
            'is_active' => $active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function orderPayload(Product $product, int $quantity, array $overrides = []): array
    {
        return array_merge([
            'client_ref' => (string) Str::uuid(),
            'paid_at' => now()->subMinutes(30)->toIso8601String(),
            'payment_method' => 'cash',
            'order_type' => 'takeaway',
            'amount_paid' => $product->price * $quantity,
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'quantity' => $quantity,
            ]],
        ], $overrides);
    }

    // ------------------------------------------------------------------ auth

    public function test_endpoint_pos_menolak_tamu(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/pos/config')->assertForbidden();
    }

    public function test_endpoint_pos_menolak_kasir_yang_belum_login(): void
    {
        // Drop the acting-as user so the request reaches the middleware anonymous.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/pos/config')->assertUnauthorized();
    }

    public function test_admin_boleh_mengakses_endpoint_pos(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/pos/config')->assertOk();
    }

    // ---------------------------------------------------------------- config

    public function test_config_mengembalikan_aturan_ppn_dan_metode_pembayaran(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '12']);

        $this->getJson('/api/pos/config')
            ->assertOk()
            ->assertJsonPath('ppn_enabled', true)
            ->assertJsonPath('ppn_percentage', 12)
            ->assertJsonPath('price_drift_tolerance', 5)
            ->assertJsonPath('payment_methods', ['cash', 'qris', 'debit', 'transfer'])
            ->assertJsonStructure(['server_time']);
    }

    public function test_config_membawa_identitas_toko_untuk_kepala_struk(): void
    {
        Setting::updateOrCreate(['key' => 'store_name'], ['value' => 'Hello Store Bandung']);
        Setting::updateOrCreate(['key' => 'store_address'], ['value' => 'Jl. Merdeka 10']);
        Setting::updateOrCreate(['key' => 'phone'], ['value' => '08123456789']);

        $this->getJson('/api/pos/config')
            ->assertOk()
            ->assertJsonPath('store_name', 'Hello Store Bandung')
            ->assertJsonPath('store_address', 'Jl. Merdeka 10')
            ->assertJsonPath('store_phone', '08123456789');
    }

    // --------------------------------------------------------------- catalog

    public function test_catalog_mengembalikan_produk_aktif_lengkap(): void
    {
        $product = $this->makeProduct('Kabel USB', 7, 45000);

        $response = $this->getJson('/api/pos/catalog')->assertOk();

        $response->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.id', $product->id)
            ->assertJsonPath('products.0.name', 'Kabel USB')
            ->assertJsonPath('products.0.sku', $product->sku)
            ->assertJsonPath('products.0.stock', 7)
            ->assertJsonPath('products.0.is_active', true)
            ->assertJsonPath('products.0.deleted', false);
    }

    public function test_catalog_delta_hanya_mengirim_perubahan_setelah_kursor(): void
    {
        $old = $this->makeProduct('Produk Lama', 5, 10000);
        $this->makeProduct('Produk Baru', 5, 20000);

        $this->travel(1)->minutes();
        $cursor = now()->toIso8601String();

        // Nothing has changed since the cursor yet.
        $this->getJson('/api/pos/catalog?since='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('count', 0);

        // The cursor is exclusive, so the edit has to land strictly after it.
        $this->travel(1)->minutes();
        $old->update(['price' => 12000]);
        $this->travel(1)->minutes();

        $this->getJson('/api/pos/catalog?since='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.id', $old->id)
            ->assertJsonPath('products.0.price', 12000);
    }

    public function test_catalog_menandai_produk_soft_deleted_untuk_dipurge_klien(): void
    {
        $product = $this->makeProduct('Produk Dihapus', 3, 10000);

        $this->travel(1)->minutes();
        $cursor = now()->toIso8601String();

        $this->travel(1)->minutes();
        $product->delete();
        $this->travel(1)->minutes();

        $this->getJson('/api/pos/catalog?since='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.deleted', true);
    }

    public function test_kursor_yang_diracik_ulang_dari_server_time_tidak_melewatkan_perubahan(): void
    {
        $product = $this->makeProduct('Kursor', 5, 10000);

        // First pull: no cursor yet, so the client gets the whole catalog.
        $first = $this->getJson('/api/pos/catalog')->assertOk();
        $cursor = $first->json('server_time');
        $this->assertNotEmpty($cursor);

        $this->travel(1)->minutes();
        $product->update(['stock' => 3]);
        $this->travel(1)->minutes();

        $this->getJson('/api/pos/catalog?since='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.stock', 3);
    }

    public function test_catalog_mencari_berdasarkan_nama_dan_sku(): void
    {
        $this->makeProduct('Powerbank', 4, 150000);
        $target = $this->makeProduct('Mouse', 9, 60000);

        $this->getJson('/api/pos/catalog?search='.urlencode('mouse'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.id', $target->id);

        $this->getJson('/api/pos/catalog?search='.urlencode($target->sku))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('products.0.id', $target->id);
    }

    // ------------------------------------------------------- online checkout

    public function test_checkout_online_membuat_order_dan_mengurangi_stok(): void
    {
        $product = $this->makeProduct('Lampu Meja', 10, 120000);

        $response = $this->postJson('/api/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
            'customer_name' => 'Siti',
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 120000,
                'quantity' => 2,
            ]],
        ])->assertOk();

        $response->assertJsonPath('status', 'ok')
            ->assertJsonPath('total', 240000)
            ->assertJsonPath('stock_conflict', false);

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', ['id' => $response->json('order_id')]);
    }

    public function test_checkout_online_menolak_stok_tidak_cukup(): void
    {
        $product = $this->makeProduct('Stok Tipis', 1, 50000);

        $this->postJson('/api/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 50000,
                'quantity' => 2,
            ]],
        ])->assertStatus(422)->assertJsonPath('status', null);

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_checkout_online_menolak_keranjang_kosong(): void
    {
        $this->postJson('/api/pos/checkout', [
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
            'items' => [],
        ])->assertStatus(422);
    }

    public function test_checkout_online_tidak_menandai_client_ref(): void
    {
        $product = $this->makeProduct('Tanpa Client', 5, 10000);

        $this->postJson('/api/pos/checkout', [
            'payment_method' => 'cash',
            'order_type' => 'dine_in',
            'amount_paid' => 10000,
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 10000,
                'quantity' => 1,
            ]],
        ])->assertOk();

        $order = Order::first();

        $this->assertNull($order->client_id);
        $this->assertNull($order->client_ref);
        $this->assertNull($order->synced_at);
    }

    // ------------------------------------------------------------------ sync

    public function test_sync_menerima_penjualan_offline_dan_mengurangi_stok(): void
    {
        $product = $this->makeProduct('Kopeku', 10, 30000);
        $clientId = (string) Str::uuid();
        $payload = $this->orderPayload($product, 2);

        $response = $this->postJson('/api/pos/sync', [
            'client_id' => $clientId,
            'orders' => [$payload],
        ])->assertOk();

        $response->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.synced', 1)
            ->assertJsonPath('summary.duplicate', 0)
            ->assertJsonPath('summary.rejected', 0)
            ->assertJsonPath('summary.stock_conflicts', 0)
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.client_ref', $payload['client_ref'])
            ->assertJsonPath('results.0.stock_conflict', false);

        $this->assertSame(8, $product->fresh()->stock);

        $order = Order::first();
        $this->assertSame($clientId, $order->client_id);
        $this->assertSame($payload['client_ref'], $order->client_ref);
        $this->assertNotNull($order->synced_at);
    }

    public function test_sync_menghormati_waktu_bayar_offline(): void
    {
        $product = $this->makeProduct('Kopi Susu', 5, 20000);
        $paidAt = now()->subHours(3);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$this->orderPayload($product, 1, ['paid_at' => $paidAt->toIso8601String()])],
        ])->assertOk();

        $this->assertEqualsWithDelta(
            $paidAt->timestamp,
            Order::first()->payment->paid_at->timestamp,
            2
        );
    }

    public function test_sync_ulang_client_ref_yang_sama_tidak_menggandakan_order(): void
    {
        $product = $this->makeProduct('Pisang', 20, 5000);
        $clientId = (string) Str::uuid();
        $payload = $this->orderPayload($product, 2);

        $this->postJson('/api/pos/sync', [
            'client_id' => $clientId,
            'orders' => [$payload],
        ])->assertOk()->assertJsonPath('results.0.status', 'synced');

        // The client never saw the response (timeout) and replays the batch.
        $second = $this->postJson('/api/pos/sync', [
            'client_id' => $clientId,
            'orders' => [$payload],
        ])->assertOk();

        $second->assertJsonPath('results.0.status', 'duplicate')
            ->assertJsonPath('summary.duplicate', 1)
            ->assertJsonPath('summary.synced', 0);

        $this->assertSame(1, Order::count());
        $this->assertSame(18, $product->fresh()->stock);
    }

    public function test_client_ref_yang_sama_dari_client_berbeda_tidak_bentrok(): void
    {
        $product = $this->makeProduct('Apel', 20, 8000);
        $ref = (string) Str::uuid();
        $otherCashier = User::factory()->create(['role' => 'cashier']);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$this->orderPayload($product, 1, ['client_ref' => $ref])],
        ])->assertOk();

        Sanctum::actingAs($otherCashier);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$this->orderPayload($product, 1, ['client_ref' => $ref])],
        ])->assertOk()->assertJsonPath('results.0.status', 'synced');

        $this->assertSame(2, Order::count());
    }

    // -------------------------------------------- the two cashiers race case

    public function test_dua_kasir_jual_unit_terakhir_yang_sama(): void
    {
        $product = $this->makeProduct('Tahu Sotong', 1, 10000);
        $kasirA = $this->cashier;
        $kasirB = User::factory()->create(['role' => 'cashier']);

        $clientA = (string) Str::uuid();
        $clientB = (string) Str::uuid();
        $payloadA = $this->orderPayload($product, 1);
        $payloadB = $this->orderPayload($product, 1);

        // Both cashiers were offline, each believed the last unit was theirs,
        // and both come back online. A syncs first, B replays right after.
        Sanctum::actingAs($kasirA);
        $a = $this->postJson('/api/pos/sync', [
            'client_id' => $clientA,
            'orders' => [$payloadA],
        ])->assertOk();

        Sanctum::actingAs($kasirB);
        $b = $this->postJson('/api/pos/sync', [
            'client_id' => $clientB,
            'orders' => [$payloadB],
        ])->assertOk();

        // A gets the stock.
        $a->assertJsonPath('results.0.stock_conflict', false);
        $this->assertSame(0, $product->fresh()->stock);

        // B still gets a real order -- the money is never lost.
        $b->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.stock_conflict', true);

        $this->assertSame(2, Order::count());
        $this->assertSame(1, Order::where('stock_conflict', true)->count());

        $orderB = Order::where('client_id', $clientB)->firstOrFail();

        $this->assertSame(1, $orderB->items()->first()->quantity);
        $this->assertEqualsWithDelta(10000.0, (float) $orderB->total, 0.01);
        $this->assertSame('paid', $orderB->payment_status);
        $this->assertStringContainsString('Tahu Sotong', (string) $orderB->conflict_reason);
        $this->assertStringContainsString('tersedia 0', (string) $orderB->conflict_reason);

        // Stock never goes negative.
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_konflik_tercatat_di_admin_notes_kalau_harga_katalog_sudah_berubah(): void
    {
        $product = $this->makeProduct('Paket Data', 5, 100000);

        $payload = $this->orderPayload($product, 1, [
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => 75000.0,
                'quantity' => 1,
            ]],
            'amount_paid' => 75000,
        ]);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$payload],
        ])->assertOk()->assertJsonPath('results.0.price_drift.0', fn ($line) => str_contains((string) $line, 'Paket Data') === true);

        $order = Order::first();
        $this->assertStringContainsString('Selisih harga', (string) $order->admin_notes);
        $this->assertEqualsWithDelta(75000.0, (float) $order->total, 0.01);
    }

    // ----------------------------------------------------------- batch edges

    public function test_satu_entri_ditolak_tidak_membatalkan_entri_lain(): void
    {
        $good = $this->makeProduct('Barang Baik', 10, 20000);
        $clientId = (string) Str::uuid();

        $response = $this->postJson('/api/pos/sync', [
            'client_id' => $clientId,
            'orders' => [
                ['client_ref' => (string) Str::uuid(), 'payment_method' => 'qris', 'order_type' => 'dine_in', 'items' => [
                    // product that does not exist -> rejected
                    ['product_id' => 999999, 'name' => 'Hantu', 'price' => 10000, 'quantity' => 1],
                ]],
                $this->orderPayload($good, 1),
            ],
        ])->assertOk();

        $response->assertJsonPath('summary.synced', 1)
            ->assertJsonPath('summary.rejected', 1)
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.1.status', 'synced');

        $this->assertSame(1, Order::count());
        $this->assertSame(9, $good->fresh()->stock);
    }

    public function test_sync_menolak_client_id_bukan_uuid(): void
    {
        $this->postJson('/api/pos/sync', [
            'client_id' => 'bukan-uuid',
            'orders' => [],
        ])->assertStatus(422);
    }

    public function test_sync_menolak_order_tanpa_client_ref(): void
    {
        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [['payment_method' => 'cash', 'order_type' => 'dine_in', 'items' => []]],
        ])->assertStatus(422);
    }

    public function test_sync_status_melaporkan_konflik_klien_bisa_baca(): void
    {
        $product = $this->makeProduct('Sisa Stok', 0, 15000);
        $clientId = (string) Str::uuid();
        $ref = (string) Str::uuid();

        $this->postJson('/api/pos/sync', [
            'client_id' => $clientId,
            'orders' => [$this->orderPayload($product, 1, ['client_ref' => $ref])],
        ])->assertOk();

        $this->getJson('/api/pos/sync/status?client_id='.urlencode($clientId))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('orders.0.client_ref', $ref)
            ->assertJsonPath('orders.0.stock_conflict', true)
            ->assertJsonStructure(['orders' => [['order_number', 'total', 'synced_at']]]);
    }

    public function test_riwayat_order_pos_hanya_menampilkan_penjualan_kasir_yang_bersamaan(): void
    {
        $mine = $this->makeProduct('Milik Saya', 20, 10000);
        $theirs = $this->makeProduct('Milik Orang', 20, 10000);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$this->orderPayload($mine, 1)],
        ])->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'cashier']));
        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [$this->orderPayload($theirs, 1)],
        ])->assertOk();

        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/pos/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.total', 10000);
    }

    public function test_riwayat_order_bisa_difilter_ke_konflik_saja(): void
    {
        $ok = $this->makeProduct('Aman', 5, 10000);
        $short = $this->makeProduct('Habis', 0, 10000);

        $this->postJson('/api/pos/sync', [
            'client_id' => (string) Str::uuid(),
            'orders' => [
                $this->orderPayload($ok, 1),
                $this->orderPayload($short, 1),
            ],
        ])->assertOk();

        $this->getJson('/api/pos/orders?conflicts_only=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.stock_conflict', true);
    }
}
