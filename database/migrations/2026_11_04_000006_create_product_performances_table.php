<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_performances', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('product_versions')->restrictOnDelete();
            $table->string('test_type', 20);
            $table->string('account_environment', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('symbol', 30);
            $table->string('timeframe', 12);
            $table->string('broker_name', 120)->nullable();
            $table->decimal('initial_capital', 20, 2)->nullable();
            $table->string('spread', 90)->nullable();
            $table->string('commission', 90)->nullable();
            $table->string('leverage', 20)->nullable();
            $table->decimal('net_profit_pct', 12, 4)->nullable();
            $table->decimal('max_drawdown_pct', 12, 4)->nullable();
            $table->decimal('win_rate_pct', 12, 4)->nullable();
            $table->decimal('profit_factor', 16, 6)->nullable();
            $table->unsignedInteger('total_trades')->nullable();
            $table->string('report_path', 500)->nullable();
            $table->string('equity_image_path', 500)->nullable();
            $table->text('methodology_note')->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['product_id', 'test_type', 'starts_on'], 'product_performances_product_type_start_idx');
        });

        DB::statement("ALTER TABLE `product_performances` ADD CONSTRAINT `product_performances_test_type_chk` CHECK (CAST(`test_type` AS BINARY) IN ('backtest', 'forward_test'))");
        DB::statement("ALTER TABLE `product_performances` ADD CONSTRAINT `product_performances_environment_chk` CHECK (CAST(`account_environment` AS BINARY) IN ('demo', 'real'))");
        DB::statement('ALTER TABLE `product_performances` ADD CONSTRAINT `product_performances_dates_chk` CHECK (`ends_on` >= `starts_on`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_performances');
    }
};
