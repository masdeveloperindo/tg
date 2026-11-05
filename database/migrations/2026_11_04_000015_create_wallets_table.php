<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique('user_id', 'wallets_user_uq');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
