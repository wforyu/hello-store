<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->after('user_id');
            $table->uuid('client_ref')->nullable()->after('client_id');
            $table->boolean('stock_conflict')->default(false)->after('payment_status');
            $table->text('conflict_reason')->nullable()->after('stock_conflict');
            $table->timestamp('synced_at')->nullable()->after('conflict_reason');
        });

        // Idempotency. A unique constraint is the dedup mechanism itself, so a
        // replayed sync (network retry after a timeout) can never create a
        // second order for one real sale -- the insert simply fails.
        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['client_id', 'client_ref'], 'orders_client_id_ref_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['stock_conflict', 'created_at'], 'orders_stock_conflict_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_stock_conflict_created_index');
            $table->dropUnique('orders_client_id_ref_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'client_id', 'client_ref', 'stock_conflict', 'conflict_reason', 'synced_at',
            ]);
        });
    }
};
