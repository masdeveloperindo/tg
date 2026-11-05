<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->text('summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_id', 'is_active'], 'guides_product_active_idx');
        });

        DB::statement('ALTER TABLE `guides` ADD CONSTRAINT `guides_active_bool_chk` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
