<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StockConflictAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    protected function makeProduct(string $name, int $stock, int $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'konflik-kategori'], ['name' => 'Konflik Kategori']);

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'price' => $price,
            'stock' => $stock,
            'sku' => 'CNF-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
    }

    protected function makeConflictedOrder(): Order
    {
        $product = $this->makeProduct('Kadaluarsa', 0, 10000);

        return Order::create([
            'user_id' => User::factory()->create(['role' => 'cashier'])->id,
            'order_number' => 'POS-TESTKONFLIK',
            'status' => 'completed',
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'client_id' => (string) Str::uuid(),
            'client_ref' => (string) Str::uuid(),
            'stock_conflict' => true,
            'conflict_reason' => "Stok 'Kadaluarsa' tidak mencukupi saat sinkronisasi (diminta 1, tersedia 0).",
            'synced_at' => now(),
        ]);
    }

    public function test_halaman_daftar_order_bisa_dirender(): void
    {
        Livewire::test(ListOrders::class)->assertOk();
    }

    public function test_filter_konflik_stok_hanya_menampilkan_order_konflik(): void
    {
        $conflict = $this->makeConflictedOrder();
        $clean = Order::create([
            'user_id' => $this->admin->id,
            'order_number' => 'POS-BERSIH',
            'status' => 'completed',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$conflict, $clean])
            ->filterTable('stock_conflict')
            ->assertCanSeeTableRecords([$conflict])
            ->assertCanNotSeeTableRecords([$clean]);
    }

    public function test_filter_dari_pos_desktop(): void
    {
        $fromClient = $this->makeConflictedOrder();
        $fromWeb = Order::create([
            'user_id' => $this->admin->id,
            'order_number' => 'POS-WEB',
            'status' => 'completed',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        Livewire::test(ListOrders::class)
            ->filterTable('dari_pos_client')
            ->assertCanSeeTableRecords([$fromClient])
            ->assertCanNotSeeTableRecords([$fromWeb]);
    }

    public function test_filter_hari_ini_tidak_menampilkan_order_kemarin(): void
    {
        $today = $this->makeOrderToday('POS-HARI-INI');

        $yesterday = $this->makeOrderToday('POS-KEMARIN');
        $yesterday->created_at = now()->subDay();
        $yesterday->saveQuietly();

        Livewire::test(ListOrders::class)
            ->assertCountTableRecords(2)
            ->filterTable('hari_ini')
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$yesterday]);
    }

    public function test_filter_menunggu_hanya_menampilkan_pending_dan_processing(): void
    {
        $pending = $this->makeOrderToday('POS-PENDING', 'pending');
        $processing = $this->makeOrderToday('POS-PROCESSING', 'processing');
        $completed = $this->makeOrderToday('POS-SELESAI', 'completed');

        Livewire::test(ListOrders::class)
            ->assertCountTableRecords(3)
            ->filterTable('menunggu')
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$pending, $processing])
            ->assertCanNotSeeTableRecords([$completed]);
    }

    protected function makeOrderToday(string $orderNumber, string $status = 'completed'): Order
    {
        return Order::create([
            'user_id' => $this->admin->id,
            'order_number' => $orderNumber,
            'status' => $status,
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);
    }

    public function test_order_dengan_konflik_bisa_ditandai_sudah_dicek(): void
    {
        $order = $this->makeConflictedOrder();
        $originalReason = $order->conflict_reason;

        Livewire::test(ListOrders::class)
            ->callTableAction('tandaiKonflikSudahDicek', $order);

        $order->refresh();

        $this->assertFalse((bool) $order->stock_conflict);
        $this->assertNull($order->conflict_reason);
        $this->assertStringContainsString('sudah dicek oleh admin', (string) $order->admin_notes);
        $this->assertStringContainsString($originalReason, (string) $order->admin_notes);
    }

    public function test_action_hanya_tampil_pada_order_konflik(): void
    {
        $conflict = $this->makeConflictedOrder();
        $clean = Order::create([
            'user_id' => $this->admin->id,
            'order_number' => 'POS-AMAN',
            'status' => 'completed',
            'subtotal' => 5000,
            'total' => 5000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
        ]);

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('tandaiKonflikSudahDicek', $conflict)
            ->assertTableActionHidden('tandaiKonflikSudahDicek', $clean);
    }

    public function test_menandai_sudah_dicek_tidak_mengubah_stok_atau_total(): void
    {
        $order = $this->makeConflictedOrder();
        $product = Product::first();
        $stockBefore = $product->stock;
        $totalBefore = $order->total;

        Livewire::test(ListOrders::class)->callTableAction('tandaiKonflikSudahDicek', $order);

        $this->assertSame($stockBefore, $product->fresh()->stock);
        $this->assertEqualsWithDelta((float) $totalBefore, (float) $order->fresh()->total, 0.01);
    }

    public function test_form_edit_order_menampilkan_detail_konflik(): void
    {
        $order = $this->makeConflictedOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('Konflik stok - perlu koreksi pembukuan')
            ->assertSee('Kadaluarsa');
    }
}
