<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', fn (Blueprint $table) => $table->text('verification_note')->nullable());
    }

    public function down(): void
    {
        Schema::table('trading_accounts', fn (Blueprint $table) => $table->dropColumn('verification_note'));
    }
};
