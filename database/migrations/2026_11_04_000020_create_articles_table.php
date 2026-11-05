<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->string('category', 24);
            $table->string('cover_path', 500)->nullable();
            $table->longText('body');
            $table->string('visibility', 12);
            $table->string('status', 12);
            $table->dateTime('published_at', 6)->nullable();
            $table->string('seo_title', 191)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['status', 'published_at'], 'articles_status_published_idx');
        });

        DB::statement("ALTER TABLE `articles` ADD CONSTRAINT `articles_category_chk` CHECK (CAST(`category` AS BINARY) IN ('signal', 'cara_trading'))");
        DB::statement("ALTER TABLE `articles` ADD CONSTRAINT `articles_visibility_chk` CHECK (CAST(`visibility` AS BINARY) IN ('public', 'client'))");
        DB::statement("ALTER TABLE `articles` ADD CONSTRAINT `articles_status_chk` CHECK (CAST(`status` AS BINARY) IN ('draft', 'published'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
