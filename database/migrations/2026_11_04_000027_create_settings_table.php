<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('key', 120)->unique();
            $table->longText('value_encrypted')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        DB::statement('ALTER TABLE `settings` ADD CONSTRAINT `settings_secret_bool_chk` CHECK (`is_secret` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
