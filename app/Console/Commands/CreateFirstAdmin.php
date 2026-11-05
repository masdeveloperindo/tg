<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateFirstAdmin extends Command
{
    protected $signature = 'tg:create-admin';

    protected $description = 'Membuat admin pertama melalui input privat; TOTP wajib saat login pertama.';

    public function handle(): int
    {
        if (User::where('role', UserRole::Admin)->exists()) {
            $this->error('Admin sudah ada. Perintah ini hanya untuk akun admin pertama.');

            return self::FAILURE;
        }

        $data = Validator::make([
            'name' => $this->ask('Nama admin'),
            'email' => mb_strtolower(trim((string) $this->ask('Email admin'))),
            'password' => $this->secret('Kata sandi (minimal 12 karakter)'),
            'password_confirmation' => $this->secret('Ulangi kata sandi'),
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ])->validate();

        return Cache::lock('auth:first-admin', 30)->block(3, function () use ($data): int {
            return DB::transaction(function () use ($data): int {
                if (User::where('role', UserRole::Admin)->exists()) {
                    $this->error('Admin sudah dibuat oleh proses lain. Tidak ada perubahan.');

                    return self::FAILURE;
                }

                $admin = (new User)->forceFill([
                    'name' => $data['name'], 'email' => $data['email'],
                    'password' => $data['password'], 'role' => UserRole::Admin,
                    'status' => UserStatus::Active,
                ]);
                $admin->save();
                Audit::record($admin, 'auth.first_admin_created', $admin, ['role' => 'admin', 'totp_required' => true]);
                $this->info('Admin dibuat. Masuk melalui halaman login dan selesaikan setup authenticator.');

                return self::SUCCESS;
            });
        });
    }
}
