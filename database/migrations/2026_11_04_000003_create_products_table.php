<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 191)->unique();
            $table->string('type', 16);
            $table->string('status', 20);
            $table->boolean('sale_enabled')->default(false);
            $table->longText('description')->nullable();
            $table->decimal('base_price_idr', 20, 2);
            $table->decimal('minimum_price_idr', 20, 2)->default(0);
            $table->string('seo_title', 191)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['type', 'status', 'sale_enabled'], 'products_type_status_sale_idx');
        });

        DB::statement("ALTER TABLE `products` ADD CONSTRAINT `products_type_chk` CHECK (CAST(`type` AS BINARY) IN ('ea', 'indicator'))");
        DB::statement("ALTER TABLE `products` ADD CONSTRAINT `products_status_chk` CHECK (CAST(`status` AS BINARY) IN ('draft', 'published', 'archived'))");
        DB::statement('ALTER TABLE `products` ADD CONSTRAINT `products_prices_chk` CHECK (`base_price_idr` >= 0 AND `minimum_price_idr` >= 0)');
        DB::statement('ALTER TABLE `products` ADD CONSTRAINT `products_sale_bool_chk` CHECK (`sale_enabled` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
