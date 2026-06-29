<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $defaults = [
            'store_address' => 'Jl. Contoh No. 123, Kelurahan, Kecamatan, Kota, Provinsi 12345',
            'phone' => '08123456789',
            'whatsapp' => '628123456789',
            'email' => 'hello@hello-store.test',
            'instagram' => 'https://instagram.com/hellostore',
            'facebook' => 'https://facebook.com/hellostore',
            'tiktok' => 'https://tiktok.com/@hellostore',
            'bank_accounts' => json_encode([
                ['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Hello Store'],
                ['bank_name' => 'Mandiri', 'account_number' => '1234567890', 'account_holder' => 'Hello Store'],
                ['bank_name' => 'BRI', 'account_number' => '1234567890', 'account_holder' => 'Hello Store'],
                ['bank_name' => 'BNI', 'account_number' => '1234567890', 'account_holder' => 'Hello Store'],
            ]),
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
