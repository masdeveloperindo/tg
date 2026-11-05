<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('title', 191);
            $table->text('body');
            $table->string('audience', 20);
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['is_active', 'starts_at', 'ends_at'], 'announcements_active_schedule_idx');
        });

        DB::statement("ALTER TABLE `announcements` ADD CONSTRAINT `announcements_audience_chk` CHECK (CAST(`audience` AS BINARY) IN ('all', 'clients', 'ib_clients'))");
        DB::statement('ALTER TABLE `announcements` ADD CONSTRAINT `announcements_active_bool_chk` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
