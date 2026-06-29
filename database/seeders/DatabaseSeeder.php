<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
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

        collect(['Samsung', 'Apple', 'Nike', 'Adidas', 'Sony', 'Xiaomi', 'H&M', 'Zara', 'UNIQLO'])->each(fn ($name) => Brand::create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_active' => true,
        ]));

        $brandIds = Brand::pluck('id')->toArray();

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
                    'brand_id' => $brandIds ? fake()->randomElement($brandIds) : null,
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

        Coupon::create([
            'code' => 'HELLO10',
            'name' => 'Diskon 10%',
            'description' => 'Diskon 10% untuk semua produk',
            'type' => 'percentage',
            'value' => 10,
            'min_order' => 50000,
            'max_discount' => 50000,
            'usage_limit' => 100,
            'usage_per_user' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'FLAT50',
            'name' => 'Diskon Rp50.000',
            'description' => 'Potongan Rp50.000 untuk belanja minimal Rp200.000',
            'type' => 'nominal',
            'value' => 50000,
            'min_order' => 200000,
            'usage_limit' => 50,
            'usage_per_user' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'GRATIS',
            'name' => 'Belanja Gratis',
            'description' => 'Gratis untuk pesanan pertama (maks Rp25.000)',
            'type' => 'nominal',
            'value' => 25000,
            'min_order' => 0,
            'max_discount' => 25000,
            'usage_limit' => 10,
            'usage_per_user' => 1,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ]);
    }
}
