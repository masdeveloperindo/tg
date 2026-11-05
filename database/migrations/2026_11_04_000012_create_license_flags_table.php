<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_flags', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->restrictOnDelete();
            $table->string('rule', 40);
            $table->string('severity', 12);
            $table->text('evidence_summary')->nullable();
            $table->char('cooldown_key', 64)->unique();
            $table->dateTime('first_seen_at', 6);
            $table->dateTime('last_seen_at', 6);
            $table->string('status', 16);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['status', 'severity', 'last_seen_at'], 'license_flags_status_severity_seen_idx');
        });

        DB::statement("ALTER TABLE `license_flags` ADD CONSTRAINT `license_flags_severity_chk` CHECK (CAST(`severity` AS BINARY) IN ('low', 'medium', 'high'))");
        DB::statement("ALTER TABLE `license_flags` ADD CONSTRAINT `license_flags_status_chk` CHECK (CAST(`status` AS BINARY) IN ('new', 'reviewed', 'ignored', 'suspended'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('license_flags');
    }
};
