<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
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
