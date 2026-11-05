<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_0900_ai_ci');
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191)->unique();
            $table->string('password', 255);
            $table->string('role', 16)->default('client');
            $table->string('status', 16)->default('active');
            $table->string('contact_channel', 16)->nullable();
            $table->text('contact_value_enc')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->text('totp_secret_enc')->nullable();
            $table->dateTime('totp_enabled_at', 6)->nullable();
            $table->dateTime('admin_last_reauth_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->index(['role', 'status'], 'users_role_status_idx');
        });

        DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_role_chk` CHECK (CAST(`role` AS BINARY) IN ('admin', 'client'))");
        DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_status_chk` CHECK (CAST(`status` AS BINARY) IN ('active', 'disabled'))");
        DB::statement("ALTER TABLE `users` ADD CONSTRAINT `users_contact_channel_chk` CHECK (CAST(`contact_channel` AS BINARY) IN ('telegram', 'whatsapp'))");
        DB::statement('ALTER TABLE `users` ADD CONSTRAINT `users_password_change_bool_chk` CHECK (`must_change_password` IN (0, 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
