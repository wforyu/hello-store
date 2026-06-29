<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'customer',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@hello-store.test',
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Kasir',
            'email' => 'kasir@hello-store.test',
            'role' => 'cashier',
        ]);

        $categories = [
            'Elektronik' => ['Smartphone', 'Laptop', 'Aksesoris Elektronik', 'Audio & Headset'],
            'Fashion Pria' => ['Kemeja', 'Celana', 'Jaket', 'Sepatu Pria'],
            'Fashion Wanita' => ['Dress', 'Blouse', 'Rok', 'Sepatu Wanita'],
            'Alat Tulis & Sekolah' => ['Pensil & Pulpen', 'Buku', 'Tas Sekolah', 'Perlengkapan Kantor'],
        ];

        foreach ($categories as $parentName => $childNames) {
            $parent = Category::factory()->create([
                'name' => $parentName,
                'slug' => str($parentName)->slug(),
                'parent_id' => null,
                'sort_order' => fake()->unique()->numberBetween(1, 99),
            ]);

            foreach ($childNames as $childName) {
                $child = Category::factory()->create([
                    'name' => $childName,
                    'slug' => str($childName)->slug(),
                    'parent_id' => $parent->id,
                    'sort_order' => fake()->numberBetween(1, 99),
                ]);

                Product::factory(rand(3, 6))->create([
                    'category_id' => $child->id,
                ]);
            }
        }

        Product::factory(5)->create(['featured' => true]);

        $customer = User::where('email', 'test@example.com')->first();
        $products = Product::all();
        foreach ($products as $product) {
            if (fake()->boolean(70)) {
                Review::factory()->create([
                    'product_id' => $product->id,
                    'user_id' => $customer->id,
                    'is_approved' => true,
                ]);
            }
        }

        ExpenseCategory::create(['name' => 'Listrik', 'slug' => 'listrik', 'description' => 'Biaya listrik toko']);
        ExpenseCategory::create(['name' => 'Gaji Karyawan', 'slug' => 'gaji-karyawan', 'description' => 'Gaji dan upah karyawan']);
        ExpenseCategory::create(['name' => 'Sewa', 'slug' => 'sewa', 'description' => 'Sewa tempat toko']);
        ExpenseCategory::create(['name' => 'Internet & Telepon', 'slug' => 'internet-telepon', 'description' => 'Biaya internet dan telepon']);
        ExpenseCategory::create(['name' => 'Operasional', 'slug' => 'operasional', 'description' => 'Biaya operasional harian']);
        ExpenseCategory::create(['name' => 'Lain-lain', 'slug' => 'lain-lain', 'description' => 'Pengeluaran lainnya']);

        Setting::create(['key' => 'ppn_enabled', 'value' => '0']);
        Setting::create(['key' => 'ppn_percentage', 'value' => '11']);
    }
}
