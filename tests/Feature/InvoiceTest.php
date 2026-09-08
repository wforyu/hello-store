<?php

namespace Tests\Feature;

use App\Mail\OrderInvoiceMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $overrides = []): Order
    {
        $category = Category::create(['name' => 'K', 'slug' => 'k-inv', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Produk Inv',
            'slug' => 'produk-inv',
            'price' => 20000,
            'stock' => 5,
            'sku' => 'SKU-INV',
            'is_active' => true,
        ]);

        return Order::create(array_merge([
            'user_id' => null,
            'order_number' => 'ORD-INV01',
            'status' => 'pending',
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'guest_phone' => '0812',
            'guest_token' => 'inv-token',
        ], $overrides));
    }

    public function test_guest_can_send_invoice_email(): void
    {
        Mail::fake();

        $order = $this->makeOrder();
        if ($order->user_id) {
            $order->update(['user_id' => null]);
        }

        $this->post(route('orders.send-invoice', ['order' => $order, 'token' => 'inv-token']))
            ->assertSessionHas('success');

        Mail::assertSent(OrderInvoiceMail::class, function ($mail) use ($order) {
            return $mail->hasTo('budi@example.com')
                && $mail->order->is($order);
        });
    }

    public function test_invalid_token_cannot_send_invoice(): void
    {
        Mail::fake();

        $order = $this->makeOrder();

        $this->post(route('orders.send-invoice', ['order' => $order, 'token' => 'salah']))
            ->assertNotFound();

        Mail::assertNothingSent();
    }
}
