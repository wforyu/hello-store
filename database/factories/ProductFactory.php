<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(rand(2, 5), true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraphs(rand(1, 3), true),
            'price' => fake()->randomFloat(2, 1000, 5000000),
            'compare_price' => fake()->optional(0.3)->randomFloat(2, 2000, 6000000),
            'cost_price' => fake()->optional(0.7)->randomFloat(2, 500, 3000000),
            'stock' => fake()->numberBetween(0, 200),
            'sku' => strtoupper(fake()->bothify('SKU-####-???')),
            'weight' => fake()->randomFloat(2, 0.1, 10),
            'is_active' => fake()->boolean(90),
            'featured' => fake()->boolean(20),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            $mainImage = 'https://picsum.photos/seed/'.Str::slug($product->name).'/640/480';
            ProductImage::create([
                'product_id' => $product->id,
                'image' => $mainImage,
                'sort_order' => 0,
            ]);
        });
    }
}
