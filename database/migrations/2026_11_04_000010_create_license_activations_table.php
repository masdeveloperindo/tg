<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->restrictOnDelete();
            $table->string('account_number', 24);
            $table->string('server_name', 100)->nullable();
            $table->string('account_type', 8)->nullable();
            $table->string('product_version', 40)->nullable();
            $table->dateTime('last_seen_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['license_id', 'account_number'], 'license_activations_license_account_uq');
            $table->index('last_seen_at', 'license_activations_last_seen_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
