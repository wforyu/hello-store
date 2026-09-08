<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'admin@hello-store.test',
        ], [
            'name' => 'Admin',
            'role' => 'admin',
            'password' => 'password',
        ]);

        User::updateOrCreate([
            'email' => 'kasir@hello-store.test',
        ], [
            'name' => 'Kasir',
            'role' => 'cashier',
            'password' => 'password',
        ]);

        Setting::updateOrCreate(['key' => 'ppn_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'ppn_percentage'], ['value' => '11']);
        Setting::updateOrCreate(['key' => 'mobile_api_url'], ['value' => config('app.url')]);

        foreach ([
            ['name' => 'Listrik', 'description' => 'Biaya listrik toko'],
            ['name' => 'Gaji Karyawan', 'description' => 'Gaji dan upah karyawan'],
            ['name' => 'Sewa', 'description' => 'Sewa tempat toko'],
            ['name' => 'Internet & Telepon', 'description' => 'Biaya internet dan telepon'],
            ['name' => 'Operasional', 'description' => 'Biaya operasional harian'],
            ['name' => 'Lain-lain', 'description' => 'Pengeluaran lainnya'],
        ] as $cat) {
            ExpenseCategory::updateOrCreate(
                ['slug' => str($cat['name'])->slug()],
                $cat
            );
        }
    }
}
