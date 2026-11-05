<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_logs', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->string('account_number_masked', 32)->nullable();
            $table->string('product_version', 40)->nullable();
            $table->string('event', 30);
            $table->string('result', 30);
            $table->char('ip_hash', 64)->nullable();
            $table->text('ip_enc')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->char('request_id', 36)->unique();
            $table->dateTime('created_at', 6);
            $table->index(['license_id', 'created_at'], 'license_logs_license_created_idx');
            $table->index(['result', 'created_at'], 'license_logs_result_created_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('license_logs');
    }
};
