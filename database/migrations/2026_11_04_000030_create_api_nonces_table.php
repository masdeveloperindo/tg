<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_nonces', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->char('nonce_sha256', 64)->unique();
            $table->dateTime('expires_at', 6);
            $table->dateTime('created_at', 6);
            $table->index('expires_at', 'api_nonces_expiry_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('api_nonces');
    }
};
