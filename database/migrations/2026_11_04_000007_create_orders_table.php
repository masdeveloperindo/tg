<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Added after both sides of the orders/licenses relationship exist.
            $table->unsignedBigInteger('renewal_license_id')->nullable();
            $table->string('mt5_account', 24);
            $table->string('mt5_server', 100);
            $table->string('account_type', 8);
            $table->string('order_type', 12);
            $table->unsignedSmallInteger('coverage_year');
            $table->decimal('price_locked_idr', 20, 2);
            $table->string('status', 20);
            $table->char('idempotency_key', 36);
            $table->dateTime('expires_at', 6);
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('payment_checked_at', 6)->nullable();
            $table->text('payment_note')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id', 'idempotency_key'], 'orders_user_idempotency_uq');
            $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_created_idx');
            $table->index(['status', 'expires_at'], 'orders_status_expiry_idx');
        });

        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_account_type_chk` CHECK (CAST(`account_type` AS BINARY) IN ('real', 'demo'))");
        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_order_type_chk` CHECK (CAST(`order_type` AS BINARY) IN ('new', 'renewal'))");
        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_status_chk` CHECK (CAST(`status` AS BINARY) IN ('waiting', 'paid', 'rejected', 'expired'))");
        DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_price_chk` CHECK (`price_locked_idr` >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
