<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokers', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('name', 120);
            $table->string('logo_path', 500)->nullable();
            $table->string('ib_link', 1000);
            $table->decimal('rate_usd_per_lot', 18, 4);
            $table->date('valid_from');
            $table->text('terms')->nullable();
            $table->boolean('is_active')->default(false);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['is_active', 'name'], 'brokers_active_name_idx');
        });

        DB::statement('ALTER TABLE `brokers` ADD CONSTRAINT `brokers_active_bool_chk` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('brokers');
    }
};
