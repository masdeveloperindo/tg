<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_versions', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('version', 40);
            $table->string('path', 500);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->text('changelog')->nullable();
            $table->string('min_supported_version', 40)->nullable();
            $table->boolean('is_active')->default(false);
            $table->dateTime('released_at', 6)->nullable();
            $table->unsignedBigInteger('active_product_id')->nullable()->storedAs('CASE WHEN is_active THEN product_id ELSE NULL END');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['product_id', 'version'], 'product_versions_product_version_uq');
            $table->unique('active_product_id', 'product_versions_active_product_uq');
            $table->index(['product_id', 'is_active', 'released_at'], 'product_versions_product_active_release_idx');
        });

        DB::statement('ALTER TABLE `product_versions` ADD CONSTRAINT `product_versions_active_bool_chk` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};
