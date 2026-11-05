<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->char('key_hash', 64)->unique();
            $table->text('key_ciphertext');
            $table->string('mt5_account', 24);
            $table->string('mt5_server', 100);
            $table->string('account_type', 8);
            $table->string('status', 12);
            $table->unsignedSmallInteger('coverage_year');
            $table->dateTime('expires_at', 6);
            $table->foreignId('issued_order_id')->constrained('orders')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id', 'product_id', 'mt5_account'], 'licenses_user_product_account_uq');
            $table->index(['user_id', 'status', 'expires_at'], 'licenses_user_status_expiry_idx');
        });

        DB::statement("ALTER TABLE `licenses` ADD CONSTRAINT `licenses_account_type_chk` CHECK (CAST(`account_type` AS BINARY) IN ('real', 'demo'))");
        DB::statement("ALTER TABLE `licenses` ADD CONSTRAINT `licenses_status_chk` CHECK (CAST(`status` AS BINARY) IN ('active', 'suspended', 'revoked'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
