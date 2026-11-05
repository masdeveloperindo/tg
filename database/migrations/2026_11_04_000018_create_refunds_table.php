<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->decimal('amount_idr', 20, 2);
            $table->string('status', 16);
            $table->string('method', 30);
            $table->text('reason');
            $table->string('evidence_path', 500)->nullable();
            $table->string('bank_reference', 120)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('refunded_at', 6)->nullable();
            $table->char('idempotency_key', 36);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['order_id', 'idempotency_key'], 'refunds_order_idempotency_uq');
            $table->index(['order_id', 'status'], 'refunds_order_status_idx');
        });

        DB::statement("ALTER TABLE `refunds` ADD CONSTRAINT `refunds_status_chk` CHECK (CAST(`status` AS BINARY) IN ('initiated', 'completed', 'rejected'))");
        DB::statement('ALTER TABLE `refunds` ADD CONSTRAINT `refunds_positive_amount_chk` CHECK (`amount_idr` > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
