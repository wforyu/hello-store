<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Elektronik', 'Fashion Pria', 'Fashion Wanita', 'Alat Tulis & Sekolah',
            'Makanan & Minuman', 'Kesehatan & Kecantikan', 'Olahraga', 'Otomotif',
            'Perlengkapan Rumah', 'Aksesoris', 'Tas & Dompet', 'Sepatu',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
