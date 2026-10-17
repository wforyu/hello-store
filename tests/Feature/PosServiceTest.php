<?php

namespace Tests\Feature;

use App\Exceptions\PosException;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StockHistory;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosService $pos;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pos = app(PosService::class);
        $this->cashier = User::factory()->create(['role' => 'cashier']);

        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '11']);
    }

    protected function makeProduct(string $name, int $stock, int $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'pos-kategori'], ['name' => 'POS Kategori']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price' => $price,
            'stock' => $stock,
            'sku' => 'POS-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function cartLine(Product $product, int $quantity = 1, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'quantity' => $quantity,
            'stock' => $product->stock,
            'discount' => 0,
            'discount_type' => 'nominal',
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    protected function cart(array $lines): Collection
    {
        return collect($lines)->values();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function checkoutOptions(array $overrides = []): array
    {
        return array_merge([
            'payment_method' => 'qris',
            'order_type' => 'dine_in',
        ], $overrides);
    }

    // ---------------------------------------------------------------- quotes

    public function test_quote_menghitung_subtotal_diskon_item_dan_total(): void
    {
        $product = $this->makeProduct('Kopi', 10, 25000);

        $quote = $this->pos->quote($this->cart([
            $this->cartLine($product, 2),
            $this->cartLine($product, 1, ['discount' => 5000]),
        ]), $this->checkoutOptions());

        $this->assertEqualsWithDelta(75000.0, $quote['subtotal'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $quote['item_discounts'], 0.01);
        $this->assertEqualsWithDelta(70000.0, $quote['after_item_discount'], 0.01);
        $this->assertEqualsWithDelta(70000.0, $quote['total'], 0.01);
        $this->assertFalse($quote['ppn_applied']);
    }

    public function test_diskon_item_persen_tidak_bisa_melebihi_nilai_barang(): void
    {
        $product = $this->makeProduct('Teh', 5, 10000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 1, ['discount' => 99999])]),
            $this->checkoutOptions()
        );

        $this->assertEqualsWithDelta(10000.0, $quote['item_discounts'], 0.01);
        $this->assertEqualsWithDelta(0.0, $quote['total'], 0.01);
    }

    public function test_diskon_item_persen_dihitung_dari_total_barang(): void
    {
        $product = $this->makeProduct('Susu', 5, 20000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 2, ['discount' => 10, 'discount_type' => 'percent'])]),
            $this->checkoutOptions()
        );

        // 2 x 20.000 = 40.000, diskon 10% = 4.000
        $this->assertEqualsWithDelta(4000.0, $quote['item_discounts'], 0.01);
        $this->assertEqualsWithDelta(36000.0, $quote['total'], 0.01);
    }

    public function test_ppn_dihitung_dari_dpp_setelah_semua_diskon(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '11']);

        $product = $this->makeProduct('Kaos', 5, 100000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['ppn' => true, 'discount' => 10000])
        );

        // DPP = 100.000 - 10.000 = 90.000, PPN 11% = 9.900
        $this->assertEqualsWithDelta(90000.0, $quote['ppn_base'], 0.01);
        $this->assertEqualsWithDelta(9900.0, $quote['ppn_amount'], 0.01);
        $this->assertEqualsWithDelta(99900.0, $quote['total'], 0.01);
    }

    public function test_ppn_diabaikan_saat_setting_nonaktif(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '0']);

        $product = $this->makeProduct('Topi', 5, 50000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['ppn' => true])
        );

        $this->assertFalse($quote['ppn_applied']);
        $this->assertEqualsWithDelta(0.0, $quote['ppn_amount'], 0.01);
    }

    public function test_rate_ppn_bebas_dari_setting(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '12']);

        $product = $this->makeProduct('Buku', 5, 50000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['ppn' => true])
        );

        $this->assertSame(12, $quote['ppn_rate']);
        $this->assertEqualsWithDelta(6000.0, $quote['ppn_amount'], 0.01);
    }

    public function test_total_tidak_pernah_negatif_saat_diskon_global_kegedean(): void
    {
        $product = $this->makeProduct('Gratis', 5, 10000);

        $quote = $this->pos->quote(
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['discount' => 999999])
        );

        $this->assertEqualsWithDelta(0.0, $quote['total'], 0.01);
    }

    // ------------------------------------------------------------- checkout

    public function test_checkout_membuat_order_payment_dan_mengurangi_stok(): void
    {
        $product = $this->makeProduct('Sabun', 10, 15000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 3)]),
            $this->checkoutOptions()
        );

        $order = $result['order'];

        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertEqualsWithDelta(45000.0, (float) $order->total, 0.01);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(3, $order->items()->first()->quantity);
        $this->assertNotNull($order->payment);
        $this->assertFalse((bool) $order->stock_conflict);
    }

    public function test_checkout_mencatat_shift_kasir_yang_aktif(): void
    {
        $shift = Shift::create([
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_balance' => 100000,
        ]);

        $product = $this->makeProduct('Rokok', 10, 25000);

        $order = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions()
        )['order'];

        $this->assertSame($shift->id, $order->shift_id);
    }

    public function test_checkout_tanpa_shift_yang_aktif_tetap_berjalan(): void
    {
        $product = $this->makeProduct('Mie', 10, 5000);

        $order = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions()
        )['order'];

        $this->assertNull($order->shift_id);
    }

    public function test_riwayat_stok_mencatat_nilai_sebelum_dan_sesudah_dengan_benar(): void
    {
        $product = $this->makeProduct('Teh Botol', 10, 8000);

        $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 4)]),
            $this->checkoutOptions()
        );

        $history = StockHistory::where('product_id', $product->id)->where('type', 'pos')->first();

        $this->assertNotNull($history);
        $this->assertSame(-4, $history->quantity_change);
        $this->assertSame(10, $history->stock_before);
        $this->assertSame(6, $history->stock_after);
    }

    public function test_checkout_menolak_keranjang_kosong(): void
    {
        $this->expectException(PosException::class);
        $this->expectExceptionMessage('Keranjang kosong!');

        $this->pos->checkout($this->cashier, collect(), $this->checkoutOptions());
    }

    public function test_checkout_menolak_metode_pembayaran_tidak_dikenal(): void
    {
        $product = $this->makeProduct('Kartu', 5, 10000);

        $this->expectException(PosException::class);

        $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['payment_method' => 'bitcoin'])
        );
    }

    public function test_checkout_tunai_menolak_jumlah_kurang_dari_total(): void
    {
        $product = $this->makeProduct('Kipas', 5, 50000);

        $this->expectException(PosException::class);
        $this->expectExceptionMessage('Jumlah dibayar kurang dari total!');

        $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['payment_method' => 'cash', 'amount_paid' => 1000])
        );
    }

    public function test_kembalian_tunai_dihitung_dari_selisih(): void
    {
        $product = $this->makeProduct('Sendok', 5, 20000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['payment_method' => 'cash', 'amount_paid' => 50000])
        );

        $this->assertEqualsWithDelta(30000.0, $result['change'], 0.01);
    }

    public function test_notes_menyimpan_ppn_dalam_format_yang_dibaca_receipt(): void
    {
        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '1']);

        $product = $this->makeProduct('Gula', 5, 20000);

        $order = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['ppn' => true, 'customer_name' => 'Budi'])
        )['order'];

        // Mirrors pos/print-receipt: split on '|', then match the segment.
        $segments = explode('|', (string) $order->notes);
        $ppnSegment = null;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (str_starts_with($segment, 'PPN ') && str_contains($segment, ': Rp ')) {
                $ppnSegment = $segment;
            }
        }

        $this->assertNotNull($ppnSegment, 'Segmen PPN tidak ditemukan di notes order.');
        $this->assertSame(1, preg_match('/^PPN (\d+)%: Rp ([\d.]+)$/', $ppnSegment, $m));
        $this->assertSame(11, (int) $m[1]);
        $this->assertSame(2200, (int) str_replace('.', '', $m[2]));
    }

    public function test_notes_tidak_berisi_ppn_saat_ppn_tidak_diaply(): void
    {
        $product = $this->makeProduct('Beras', 5, 60000);

        $order = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1)]),
            $this->checkoutOptions(['ppn' => true])
        )['order'];

        $this->assertStringNotContainsString('PPN', (string) $order->notes);
    }

    // -------------------------------------------------------- strict vs clamp

    public function test_mode_strict_menolak_order_kalau_stok_tidak_cukup(): void
    {
        $product = $this->makeProduct('Lampu', 1, 30000);

        try {
            $this->pos->checkout(
                $this->cashier,
                $this->cart([$this->cartLine($product, 2)]),
                $this->checkoutOptions(['mode' => PosService::MODE_STRICT])
            );
            $this->fail('Harus melempar PosException.');
        } catch (PosException $e) {
            $this->assertStringContainsString('tidak mencukupi', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_mode_clamp_menerima_order_dan_menandai_konflik_stok(): void
    {
        $product = $this->makeProduct('Lampu', 1, 30000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 2)]),
            $this->checkoutOptions([
                'mode' => PosService::MODE_CLAMP,
                'client_id' => (string) Str::uuid(),
                'client_ref' => (string) Str::uuid(),
            ])
        );

        $order = $result['order'];

        $this->assertNotEmpty($result['conflicts']);
        $this->assertTrue((bool) $order->stock_conflict);
        $this->assertStringContainsString('Lampu', (string) $order->conflict_reason);
        $this->assertNotNull($order->synced_at);

        // Order tetapDibuat penuh: uang kasir tidak pernah hilang.
        $this->assertSame(2, $order->items()->first()->quantity);
        $this->assertEqualsWithDelta(60000.0, (float) $order->total, 0.01);

        // Stok diklem ke 0, tidak jadi negatif.
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_mode_clamp_tanpa_konflik_tidak_menandai_order(): void
    {
        $product = $this->makeProduct('Stiker', 5, 5000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 2)]),
            $this->checkoutOptions([
                'mode' => PosService::MODE_CLAMP,
                'client_id' => (string) Str::uuid(),
                'client_ref' => (string) Str::uuid(),
            ])
        );

        $this->assertSame([], $result['conflicts']);
        $this->assertFalse((bool) $result['order']->stock_conflict);
        $this->assertNull($result['order']->conflict_reason);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_mode_clamp_menolak_produk_yang_tidak_ada(): void
    {
        $cart = collect([[
            'product_id' => 999999,
            'name' => 'Produk Hilang',
            'price' => 10000.0,
            'quantity' => 1,
            'discount' => 0,
            'discount_type' => 'nominal',
        ]]);

        $this->expectException(PosException::class);

        $this->pos->checkout(
            $this->cashier,
            $cart,
            $this->checkoutOptions(['mode' => PosService::MODE_CLAMP])
        );
    }

    public function test_riwayat_stok_hanya_mencatat_pengurangan_yang_benar(): void
    {
        $product = $this->makeProduct('Sapu', 2, 12000);

        $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 5)]),
            $this->checkoutOptions(['mode' => PosService::MODE_CLAMP])
        );

        $history = StockHistory::where('product_id', $product->id)->where('type', 'pos')->first();

        $this->assertNotNull($history);
        $this->assertSame(-2, $history->quantity_change);
        $this->assertSame(2, $history->stock_before);
        $this->assertSame(0, $history->stock_after);
    }

    // ------------------------------------------------------------ price drift

    public function test_harga_yang_dijual_tetap_berlaku_walau_katalog_berbeda(): void
    {
        $product = $this->makeProduct('Jam', 5, 100000);

        $result = $this->pos->checkout(
            $this->cashier,
            // Kasir menjual dengan harga lama yang masih tercetak di struk.
            $this->cart([$this->cartLine($product, 1, ['price' => 90000.0])]),
            $this->checkoutOptions(['mode' => PosService::MODE_CLAMP])
        );

        $this->assertEqualsWithDelta(90000.0, (float) $result['order']->total, 0.01);
    }

    public function test_selisih_harga_lebih_dari_toleransi_ditandai_di_admin_notes(): void
    {
        $product = $this->makeProduct('Kamera', 5, 1000000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1, ['price' => 800000.0])]),
            $this->checkoutOptions(['mode' => PosService::MODE_CLAMP])
        );

        $this->assertNotEmpty($result['price_drift']);
        $this->assertStringContainsString('Kamera', (string) $result['order']->admin_notes);
    }

    public function test_selisih_harga_kecil_tidak_ditandai(): void
    {
        $product = $this->makeProduct('Tas', 5, 100000);

        $result = $this->pos->checkout(
            $this->cashier,
            $this->cart([$this->cartLine($product, 1, ['price' => 99000.0])]),
            $this->checkoutOptions(['mode' => PosService::MODE_CLAMP])
        );

        $this->assertSame([], $result['price_drift']);
        $this->assertNull($result['order']->admin_notes);
    }
}
