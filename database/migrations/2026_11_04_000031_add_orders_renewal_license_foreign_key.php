<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('renewal_license_id', 'orders_renewal_license_fk')
                ->references('id')->on('licenses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign('orders_renewal_license_fk');
        });
    }
};
