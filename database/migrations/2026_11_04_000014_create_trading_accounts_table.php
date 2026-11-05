<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_accounts', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('broker_id')->constrained('brokers')->restrictOnDelete();
            $table->string('account_number', 24);
            $table->string('status', 16);
            $table->text('evidence_note')->nullable();
            $table->dateTime('verified_at', 6)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['broker_id', 'account_number'], 'trading_accounts_broker_account_uq');
            $table->index(['user_id', 'status'], 'trading_accounts_user_status_idx');
        });

        DB::statement("ALTER TABLE `trading_accounts` ADD CONSTRAINT `trading_accounts_status_chk` CHECK (CAST(`status` AS BINARY) IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_accounts');
    }
};
