<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 5): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'kategori-test'],
            ['name' => 'Kategori Test', 'is_active' => true]
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Security',
            'slug' => 'produk-security-'.uniqid(),
            'price' => 100000,
            'stock' => $stock,
            'sku' => 'SKU-SEC-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeOrder(
        Product $product,
        string $status = 'pending',
        int $qty = 2,
        ?User $user = null,
        string $paymentStatus = 'unpaid'
    ): Order {
        $order = Order::create([
            'user_id' => $user?->id,
            'order_number' => 'ORD-'.strtoupper(bin2hex(random_bytes(4))),
            'status' => $status,
            'subtotal' => $product->price * $qty,
            'shipping_cost' => 0,
            'total' => $product->price * $qty,
            'payment_method' => 'cod',
            'payment_status' => $paymentStatus,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => $qty,
            'subtotal' => $product->price * $qty,
        ]);

        return $order;
    }

    public function test_cancel_restores_stock_exactly_once_on_replay(): void
    {
        $product = $this->makeProduct(stock: 3);
        $user = User::factory()->create();
        $order = $this->makeOrder($product, qty: 2, user: $user);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertSessionHas('success');

        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals('cancelled', $order->fresh()->status);

        // replay harus ditolak dan TIDAK menambah stok lagi
        $this->post(route('orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertEquals(5, $product->fresh()->stock);
    }

    public function test_cancel_cannot_be_used_by_other_customer(): void
    {
        $product = $this->makeProduct(stock: 3);
        $owner = User::factory()->create();
        $order = $this->makeOrder($product, qty: 2, user: $owner);

        $this->actingAs(User::factory()->create())
            ->post(route('orders.cancel', $order))
            ->assertNotFound();

        $this->assertEquals(3, $product->fresh()->stock);
        $this->assertEquals('pending', $order->fresh()->status);
    }

    public function test_confirm_received_grants_points_exactly_once_on_replay(): void
    {
        $product = $this->makeProduct(stock: 5);
        $user = User::factory()->create(['points' => 0]);
        $order = $this->makeOrder($product, status: 'shipped', qty: 1, user: $user);

        $this->actingAs($user)
            ->post(route('orders.confirm-received', $order))
            ->assertSessionHas('success');

        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertEquals((int) floor($order->total * 0.10), $user->fresh()->points);

        // replay: status sudah delivered, poin & total_spent tidak boleh naik lagi
        $this->post(route('orders.confirm-received', $order))
            ->assertSessionHas('error');

        $this->assertEquals((int) floor($order->total * 0.10), $user->fresh()->points);
    }

    public function test_redeem_points_cannot_go_below_zero(): void
    {
        $user = User::factory()->create(['points' => 50]);

        $this->expectException(\InvalidArgumentException::class);

        try {
            $user->redeemPoints(500, 'overdraw');
        } finally {
            $this->assertEquals(50, $user->fresh()->points);
        }
    }

    public function test_pos_receipt_of_other_cashier_is_forbidden(): void
    {
        $product = $this->makeProduct();
        $other = User::factory()->create(['role' => 'cashier']);
        $order = $this->makeOrder($product, status: 'completed', user: $other, paymentStatus: 'paid');

        $this->actingAs(User::factory()->create(['role' => 'cashier']))
            ->get(route('pos.print', $order))
            ->assertForbidden();
    }

    public function test_pos_receipt_of_own_transaction_is_allowed(): void
    {
        $product = $this->makeProduct();
        $cashier = User::factory()->create(['role' => 'cashier']);
        $order = $this->makeOrder($product, status: 'completed', user: $cashier, paymentStatus: 'paid');

        $this->actingAs($cashier)
            ->get(route('pos.print', $order))
            ->assertOk();
    }

    public function test_admin_can_print_any_pos_receipt(): void
    {
        $product = $this->makeProduct();
        $other = User::factory()->create(['role' => 'cashier']);
        $order = $this->makeOrder($product, status: 'completed', user: $other, paymentStatus: 'paid');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('pos.print', $order))
            ->assertOk();
    }

    public function test_security_headers_are_present(): void
    {
        $this->get(route('products.index'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_coupon_consume_usage_respects_limit(): void
    {
        $coupon = Coupon::create([
            'code' => 'KUOTA1',
            'name' => 'Kupon Kuota',
            'type' => 'nominal',
            'value' => 10000,
            'min_order' => 0,
            'usage_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->consumeUsage());
        $this->assertEquals(1, $coupon->fresh()->used_count);

        // kuota habis → harus menolak
        $this->assertFalse($coupon->consumeUsage());
        $this->assertEquals(1, $coupon->fresh()->used_count);
    }

    public function test_coupon_without_limit_is_always_consumable(): void
    {
        $coupon = Coupon::create([
            'code' => 'UNLIMITED',
            'name' => 'Kupon Tanpa Batas',
            'type' => 'percentage',
            'value' => 10,
            'min_order' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->consumeUsage());
        $this->assertTrue($coupon->consumeUsage());
        $this->assertTrue($coupon->consumeUsage());
        $this->assertEquals(3, $coupon->fresh()->used_count);
    }

    public function test_registered_user_cannot_escalate_role_via_api(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Calon Admin',
            'email' => 'calon-admin@example.com',
            'password' => 'rahasia12345',
            'password_confirmation' => 'rahasia12345',
            'role' => 'admin',
        ]);

        $user = User::where('email', 'calon-admin@example.com')->firstOrFail();

        $this->assertEquals('customer', $user->role);
        $this->assertEquals(0, $user->points);
    }

    public function test_role_is_not_mass_assignable_on_user_model(): void
    {
        $user = User::create([
            'name' => 'Mass Assign',
            'email' => 'mass-assign@example.com',
            'password' => bcrypt('rahasia12345'),
            'role' => 'admin',
        ]);

        $this->assertNotEquals('admin', $user->fresh()->role);
    }
}
