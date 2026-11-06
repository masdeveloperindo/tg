<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('license_flags', fn (Blueprint $t) => $t->text('review_note')->nullable());
    }

    public function down(): void
    {
        Schema::table('license_flags', fn (Blueprint $t) => $t->dropColumn('review_note'));
    }
};
