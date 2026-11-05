<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rebate_entries', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('trading_account_id')->constrained('trading_accounts')->restrictOnDelete();
            $table->char('period', 7);
            $table->decimal('lots', 18, 4);
            $table->decimal('amount_usd', 20, 4);
            $table->string('source_reference', 128);
            $table->foreignId('correction_of_id')->nullable()->constrained('rebate_entries')->restrictOnDelete();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->text('memo')->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['trading_account_id', 'source_reference'], 'rebate_entries_account_source_uq');
            $table->index(['trading_account_id', 'period'], 'rebate_entries_account_period_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rebate_entries');
    }
};
