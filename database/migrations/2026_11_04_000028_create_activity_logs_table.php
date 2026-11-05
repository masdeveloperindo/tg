<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->char('request_id', 36);
            $table->json('summary_json')->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['actor_id', 'created_at'], 'activity_logs_actor_created_idx');
            $table->index(['subject_type', 'subject_id'], 'activity_logs_subject_idx');
            $table->index('request_id', 'activity_logs_request_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
