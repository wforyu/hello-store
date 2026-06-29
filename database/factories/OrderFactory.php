<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.strtoupper(fake()->bothify('####-????-####')),
            'status' => fake()->randomElement(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled']),
            'subtotal' => fake()->randomFloat(2, 50000, 5000000),
            'shipping_cost' => fake()->randomFloat(2, 0, 50000),
            'total' => fn (array $attrs) => $attrs['subtotal'] + $attrs['shipping_cost'],
            'payment_method' => fake()->randomElement(['manual_transfer', 'cod']),
            'payment_status' => fn (array $attrs) => in_array($attrs['status'], ['delivered', 'shipped', 'processing']) ? 'paid' : 'unpaid',
        ];
    }
}
