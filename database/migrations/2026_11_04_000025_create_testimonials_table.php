<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('display_name', 120);
            $table->text('content');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->string('source', 255);
            $table->string('consent_record', 500);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(false);
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_id', 'is_visible', 'sort_order'], 'testimonials_product_visible_sort_idx');
        });

        DB::statement('ALTER TABLE `testimonials` ADD CONSTRAINT `testimonials_rating_chk` CHECK (`rating` BETWEEN 1 AND 5 OR `rating` IS NULL)');
        DB::statement('ALTER TABLE `testimonials` ADD CONSTRAINT `testimonials_visible_bool_chk` CHECK (`is_visible` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
