<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->longText('body');
            $table->string('seo_title', 191)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_published')->default(false);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        DB::statement('ALTER TABLE `pages` ADD CONSTRAINT `pages_published_bool_chk` CHECK (`is_published` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
