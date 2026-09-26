<?php

namespace Tests\Feature;

use App\Filament\Widgets\TotalAsetWidget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TotalAsetWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function summary(): array
    {
        return app(TotalAsetWidget::class)->assetSummary();
    }

    private function makeProduct(string $name, int $stock, int $price, ?int $cost): Product
    {
        $category = Category::firstOrCreate(['slug' => 'kategori-aset'], ['name' => 'Kategori Aset']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'price' => $price,
            'cost_price' => $cost,
            'stock' => $stock,
            'sku' => 'SKU-'.str($name)->upper(),
            'is_active' => true,
        ]);
    }

    public function test_stok_modal_dan_nilai_jual_menjumlahkan_produk_beserta_varian(): void
    {
        // produk A: 10 unit, jual 100rb, modal 60rb
        $a = $this->makeProduct('Produk A', 10, 100000, 60000);
        // produk B: 5 unit, jual 50rb, modal 40rb
        $this->makeProduct('Produk B', 5, 50000, 40000);
        // varian dari A: 3 unit, jual 120rb, modal ikut induk 60rb
        ProductVariant::create([
            'product_id' => $a->id,
            'name' => 'Merah',
            'price' => 120000,
            'stock' => 3,
            'is_active' => true,
        ]);

        $s = $this->summary();

        // 10 + 5 + 3 varian
        $this->assertEquals(18, $s['total_stock']);
        // 2 produk + 1 varian
        $this->assertEquals(3, $s['total_sku']);

        // (10 x 60rb) + (5 x 40rb) + (3 x 60rb) = 600rb + 200rb + 180rb
        $this->assertEquals(980000, $s['modal_persediaan']);
        // (10 x 100rb) + (5 x 50rb) + (3 x 120rb) = 1jt + 250rb + 360rb
        $this->assertEquals(1610000, $s['nilai_jual_persediaan']);
        $this->assertEquals(630000, $s['potensi_laba_stok']);
    }

    public function test_produk_tanpa_modal_dihitung_dan_ditandai(): void
    {
        $this->makeProduct('Produk Tanpa Modal', 4, 30000, null);
        $this->makeProduct('Produk Modal Nol', 2, 20000, 0);
        $this->makeProduct('Produk Ada Modal', 1, 50000, 25000);

        $s = $this->summary();

        $this->assertEquals(2, $s['produk_tanpa_modal']);
        // hanya produk yang punya modal yang dihitung: 1 x 25rb
        $this->assertEquals(25000, $s['modal_persediaan']);
    }

    public function test_produk_terhapus_tidak_dihitung_sebagai_aset(): void
    {
        $this->makeProduct('Produk Aktif', 5, 40000, 20000);

        $trashed = $this->makeProduct('Produk Dibuang', 100, 40000, 20000);
        $trashed->delete();

        $s = $this->summary();

        $this->assertEquals(5, $s['total_stock']);
        $this->assertEquals(100000, $s['modal_persediaan']);
    }

    public function test_laba_kotor_dan_bersih(): void
    {
        $produk = $this->makeProduct('Produk Laba', 10, 100000, 60000);

        $order = Order::create([
            'order_number' => 'ORD-ASET-1',
            'status' => 'completed',
            'subtotal' => 200000,
            'total' => 200000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'product_price' => 100000,
            'quantity' => 2,
            'subtotal' => 200000,
        ]);

        $category = ExpenseCategory::create(['name' => 'Operasional', 'slug' => 'operasional-aset']);
        Expense::create([
            'expense_category_id' => $category->id,
            'amount' => 50000,
            'description' => 'Beban',
            'expense_date' => now()->toDateString(),
        ]);

        $s = $this->summary();

        $this->assertEquals(200000, $s['pendapatan']);
        $this->assertEquals(120000, $s['hpp']);   // 2 x 60rb
        $this->assertEquals(50000, $s['pengeluaran']);
        $this->assertEquals(80000, $s['laba_kotor']);    // 200rb - 120rb
        $this->assertEquals(30000, $s['laba_bersih']);   // 80rb - 50rb
    }

    public function test_pesanan_belum_lunas_tidak_dihitung_sebagai_pendapatan(): void
    {
        $this->makeProduct('Produk X', 3, 50000, 20000);

        Order::create([
            'order_number' => 'ORD-UNPAID',
            'status' => 'pending',
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);

        $this->assertEquals(0, $this->summary()['pendapatan']);
    }

    public function test_toko_kosong_tidak_ng_hitung_error(): void
    {
        $s = $this->summary();

        $this->assertEquals(0, $s['total_stock']);
        $this->assertEquals(0, $s['modal_persediaan']);
        $this->assertEquals(0, $s['laba_kotor']);
        $this->assertEquals(0, $s['laba_bersih']);
    }

    public function test_widget_bisa_dirender_di_panel(): void
    {
        $this->makeProduct('Produk Render', 7, 80000, 50000);

        Livewire::test(TotalAsetWidget::class)
            ->assertOk()
            ->assertSee('Total Aset & Laba')
            ->assertSee('Jumlah Produk')
            ->assertSee('7 unit')
            ->assertSee('Total Modal Persediaan')
            ->assertSee('350.000')
            ->assertSee('Nilai Jual Persediaan')
            ->assertSee('Potensi Laba dari Stok')
            ->assertSee('Laba Kotor')
            ->assertSee('Laba Bersih');
    }

    public function test_widget_terdaftar_di_halaman_dashboard(): void
    {
        // Regresi: panel mendaftarkan widget lewat list eksplisit di
        // AdminPanelProvider, bukan auto-discovery. Kalau class-nya lupa
        // didaftarkan, widget tidak muncul sama sekali di dashboard meski
        // file-nya ada.
        //
        // Widget di-lazy-load (x-intersect), jadi HTML awal cuma berisi
        // placeholder "Loading..". Yang dicek di sini adalah KOMPONENNYA
        // terdaftar, bukan isi card-nya.
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('App\Filament\Widgets\TotalAsetWidget');
    }
}
