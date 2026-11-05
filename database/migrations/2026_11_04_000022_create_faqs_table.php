<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('question', 500);
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_id', 'is_active', 'sort_order'], 'faqs_product_active_sort_idx');
        });

        DB::statement('ALTER TABLE `faqs` ADD CONSTRAINT `faqs_active_bool_chk` CHECK (`is_active` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
