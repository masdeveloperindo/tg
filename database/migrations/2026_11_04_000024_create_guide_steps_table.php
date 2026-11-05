<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_steps', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->restrictOnDelete();
            $table->unsignedInteger('step_no');
            $table->text('body');
            $table->string('image_path', 500)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['guide_id', 'step_no'], 'guide_steps_guide_step_uq');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('guide_steps');
    }
};
