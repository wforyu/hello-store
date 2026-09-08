<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_checkout_with_empty_cart_redirects(): void
    {
        $this->get(route('checkout'))
            ->assertRedirect(route('products.index'));
    }

    public function test_guest_can_place_order_and_get_token(): void
    {
        $category = Category::create([
            'name' => 'Test Kategori',
            'slug' => 'test-kategori',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Guest Test',
            'slug' => 'produk-guest-test',
            'description' => 'Deskripsi',
            'price' => 50000,
            'stock' => 10,
            'sku' => 'SKU-GUEST-1',
            'weight' => 500,
            'is_active' => true,
        ]);

        $this->withSession(['cart' => collect([
            [
                'product_id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->price,
                'image' => null,
                'quantity' => 2,
                'stock' => $product->stock,
            ],
        ])]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSee('Data Pemesanan')
            ->assertSee('guest_name');

        // Note: getCartWeight expects weight in grams; product weight 500.
        $this->post(route('checkout.place'), [
            'guest_name' => 'Budi Guest',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '081234567890',
            'address_recipient' => 'Budi Guest',
            'address_phone' => '081234567890',
            'address_street' => 'Jl. Merdeka No. 1',
            'address_city' => 'Bandung',
            'address_province' => 'Jawa Barat',
            'address_postal_code' => '40123',
            'payment_method' => 'cod',
            'notes' => 'Cepat ya',
            'shipping_courier' => 'flat',
            'shipping_service' => 'Reguler',
            'shipping_cost' => 15000,
        ])->assertSessionHas('success');

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
        $this->assertNotNull($order->guest_token);
        $this->assertEquals('budi@example.com', $order->guest_email);

        $this->assertTrue($order->isGuest());
        $this->assertEquals('Budi Guest', $order->customer_name);
        $this->assertEquals('budi@example.com', $order->customer_email);

        $this->assertEquals(100000, (float) $order->subtotal);
        $this->assertEquals(15000, (float) $order->shipping_cost);
        $this->assertEquals(115000, (float) $order->total);
    }

    public function test_guest_order_detail_requires_valid_token(): void
    {
        $category = Category::create(['name' => 'K', 'slug' => 'k', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk A',
            'slug' => 'produk-a',
            'price' => 10000,
            'stock' => 5,
            'sku' => 'SKU-A',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => null,
            'order_number' => 'ORD-TESTABC',
            'status' => 'pending',
            'subtotal' => 10000,
            'shipping_cost' => 0,
            'total' => 10000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '0812',
            'guest_token' => 'sekret-token-123',
        ]);

        $this->get(route('orders.show', ['order' => $order->id]))
            ->assertNotFound();

        $this->get(route('orders.show', ['order' => $order->id, 'token' => 'salah']))
            ->assertNotFound();

        $this->get(route('orders.show', ['order' => $order->id, 'token' => 'sekret-token-123']))
            ->assertOk();
    }

    public function test_track_order_lookup_by_number_and_email(): void
    {
        $category = Category::create(['name' => 'K', 'slug' => 'k', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk B',
            'slug' => 'produk-b',
            'price' => 20000,
            'stock' => 5,
            'sku' => 'SKU-B',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => null,
            'order_number' => 'ORD-TRACK1',
            'status' => 'pending',
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '0812',
            'guest_token' => 'tok-abc',
        ]);

        $this->get(route('track.order'))->assertOk();

        $this->post(route('track.order.lookup'), [
            'order_number' => 'ORD-TRACK1',
            'guest_email' => 'budi@example.com',
        ])->assertRedirect(route('orders.show', ['order' => $order->id, 'token' => 'tok-abc']));

        $this->post(route('track.order.lookup'), [
            'order_number' => 'ORD-TRACK1',
            'guest_email' => 'salah@example.com',
        ])->assertSessionHasErrors('not_found');
    }
}
