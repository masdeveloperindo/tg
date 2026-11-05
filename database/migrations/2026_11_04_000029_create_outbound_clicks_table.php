<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_clicks', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->char('visitor_hash', 64)->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['target_type', 'target_id', 'created_at'], 'outbound_clicks_target_created_idx');
        });

        DB::statement("ALTER TABLE `outbound_clicks` ADD CONSTRAINT `outbound_clicks_type_chk` CHECK (CAST(`target_type` AS BINARY) IN ('telegram', 'broker'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_clicks');
    }
};
