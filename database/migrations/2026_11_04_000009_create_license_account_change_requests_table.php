<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_account_change_requests', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('license_id')->constrained('licenses', indexName: 'license_changes_license_fk')->restrictOnDelete();
            $table->string('old_account', 24);
            $table->string('new_account', 24);
            $table->string('new_server', 100);
            $table->string('status', 12);
            $table->string('reason', 500)->nullable();
            $table->dateTime('requested_at', 6);
            $table->foreignId('decided_by')->nullable()->constrained('users', indexName: 'license_changes_decider_fk')->restrictOnDelete();
            $table->dateTime('decided_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['license_id', 'status', 'requested_at'], 'license_changes_license_status_requested_idx');
        });

        DB::statement("ALTER TABLE `license_account_change_requests` ADD CONSTRAINT `license_changes_status_chk` CHECK (CAST(`status` AS BINARY) IN ('pending', 'approved', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('license_account_change_requests');
    }
};
