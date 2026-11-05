<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('media_type', 16);
            $table->string('disk_path', 500)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('caption', 255)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('section', 25);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_id', 'section', 'sort_order'], 'product_media_product_section_sort_idx');
        });

        DB::statement("ALTER TABLE `product_media` ADD CONSTRAINT `product_media_type_chk` CHECK (CAST(`media_type` AS BINARY) IN ('image', 'video_link'))");
        DB::statement("ALTER TABLE `product_media` ADD CONSTRAINT `product_media_section_chk` CHECK (CAST(`section` AS BINARY) IN ('preview', 'tutorial'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
