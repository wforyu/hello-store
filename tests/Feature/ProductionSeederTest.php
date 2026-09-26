<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_admin_and_cashier_without_hardcoded_password(): void
    {
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'admin@hello-store.test')->firstOrFail();
        $cashier = User::where('email', 'kasir@hello-store.test')->firstOrFail();

        $this->assertEquals('admin', $admin->role);
        $this->assertEquals('cashier', $cashier->role);

        // password harus acak, bukan string "password" yang dulu ter-hardcode
        $this->assertFalse(
            Hash::check('password', $admin->password),
            'Password admin masih "password" — hardcoded password belum hilang.'
        );
        $this->assertFalse(
            Hash::check('password', $cashier->password),
            'Password kasir masih "password" — hardcoded password belum hilang.'
        );
    }

    public function test_rerunning_seeder_does_not_reset_existing_password(): void
    {
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'admin@hello-store.test')->firstOrFail();
        $original = $admin->password;

        // seeder dijalankan LAGI (misal karena deploy ulang)
        $this->seed(ProductionSeeder::class);

        $admin->refresh();

        // password harus tetap sama
        $this->assertEquals($original, $admin->password);
    }

    public function test_rerunning_seeder_keeps_real_password_still_working(): void
    {
        $this->seed(ProductionSeeder::class);

        $admin = User::where('email', 'admin@hello-store.test')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('PasswordProduksiKuat123!')])->save();

        $this->seed(ProductionSeeder::class);

        $admin->refresh();

        $this->assertTrue(Hash::check('PasswordProduksiKuat123!', $admin->password));
    }
}
