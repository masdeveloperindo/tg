<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_images', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->string('path', 500);
            $table->string('alt_text', 255)->nullable();
            $table->unsignedBigInteger('bytes');
            $table->dateTime('created_at', 6);
            $table->index('article_id', 'article_images_article_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('article_images');
    }
};
