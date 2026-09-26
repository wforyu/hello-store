<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertUser('admin@hello-store.test', 'Admin', 'admin');
        $this->upsertUser('kasir@hello-store.test', 'Kasir', 'cashier');

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

    /**
     * Membuat akun kalau belum ada. Password akun yang SUDAH ADA tidak pernah
     * disentuh — jadi seeder ini aman dijalankan ulang tanpa mengubah password
     * produksi yang sedang dipakai.
     *
     * Password tidak pernah hardcoded. Akun baru mendapat password acak yang
     * dicetak sekali ke terminal.
     */
    private function upsertUser(string $email, string $name, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'role' => $role,
        ]);

        if ($user->exists) {
            $user->save();

            return;
        }

        $password = Str::password(24);

        $user->forceFill(['password' => $password])->save();

        $this->command?->newLine();
        $this->command?->warn("Akun baru dibuat: {$email} (role: {$role})");
        $this->command?->warn("Password: {$password}");
        $this->command?->warn('Hanya ditampilkan SEKALI. Simpan sekarang, lalu ganti dari dashboard.');
        $this->command?->newLine();
    }
}
