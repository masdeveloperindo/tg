<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount_usd', 20, 4);
            $table->decimal('fx_rate_idr', 20, 6);
            $table->decimal('amount_idr', 20, 2);
            $table->text('destination_enc');
            $table->string('destination_last4', 8)->nullable();
            $table->string('status', 16);
            $table->char('idempotency_key', 36);
            $table->dateTime('reserved_at', 6);
            $table->dateTime('paid_at', 6)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('bank_reference', 120)->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['user_id', 'idempotency_key'], 'withdrawals_user_idempotency_uq');
            $table->index(['user_id', 'status', 'created_at'], 'withdrawals_user_status_created_idx');
        });

        DB::statement("ALTER TABLE `withdrawals` ADD CONSTRAINT `withdrawals_status_chk` CHECK (CAST(`status` AS BINARY) IN ('requested', 'processing', 'paid', 'rejected', 'cancelled'))");
        DB::statement('ALTER TABLE `withdrawals` ADD CONSTRAINT `withdrawals_positive_amounts_chk` CHECK (`amount_usd` > 0 AND `fx_rate_idr` > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
