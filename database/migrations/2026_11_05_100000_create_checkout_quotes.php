<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_quotes', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('renewal_license_id')->nullable()->constrained('licenses')->restrictOnDelete();
            $table->decimal('amount_idr', 20, 2);
            $table->unsignedSmallInteger('coverage_year');
            $table->dateTime('coverage_expires_at', 6);
            $table->dateTime('expires_at', 6);
            $table->unsignedInteger('payment_minutes');
            $table->json('pricing_snapshot');
            $table->longText('payment_details');
            $table->dateTime('created_at', 6);
            $table->index(['user_id', 'expires_at']);
        });
        DB::statement('ALTER TABLE checkout_quotes ADD CONSTRAINT checkout_quotes_amount_check CHECK (amount_idr > 0), ADD CONSTRAINT checkout_quotes_payment_minutes_check CHECK (payment_minutes BETWEEN 1 AND 10080)');
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignUuid('checkout_quote_id')->nullable()->unique()->constrained('checkout_quotes')->restrictOnDelete();
            $table->string('payment_method_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('checkout_quote_id');
            $table->dropColumn('payment_method_id');
        });
        Schema::dropIfExists('checkout_quotes');
    }
};
