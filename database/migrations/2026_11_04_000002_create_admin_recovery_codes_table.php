<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_recovery_codes', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('code_hash', 64);
            $table->dateTime('used_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['user_id', 'code_hash'], 'admin_recovery_user_code_uq');
            $table->index(['user_id', 'used_at'], 'admin_recovery_user_used_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('admin_recovery_codes');
    }
};
