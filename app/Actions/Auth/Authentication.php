<?php

namespace App\Actions\Auth;

use App\Enums\ContactChannel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Security\Access;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Authentication
{
    // Timing sentinel only: this hash is never a credential for any account.
    private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$OHlVRFBha0VpckRFQTRZQg$zPVe47HWmTF30+haTR4ydXqo0xH2N8YIByyYGAWiQ0k';

    public function register(array $input): User
    {
        $key = 'auth:register:ip:'.hash('sha256', (string) request()->ip());

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['email' => 'Pendaftaran belum dapat diproses. Coba lagi nanti.']);
        }

        RateLimiter::hit($key, 900);
        $channel = $input['contact_channel'] ?? null;
        $contact = is_string($input['contact_value'] ?? null) ? trim($input['contact_value']) : ($input['contact_value'] ?? null);

        if ($channel === ContactChannel::Telegram->value && is_string($contact)) {
            $contact = preg_replace('/^@/', '', $contact);
        }

        $data = Validator::make([
            'name' => is_string($input['name'] ?? null) ? trim($input['name']) : ($input['name'] ?? null),
            'email' => is_string($input['email'] ?? null) ? $this->normalizeEmail($input['email']) : ($input['email'] ?? null),
            'password' => $input['password'] ?? null,
            'password_confirmation' => $input['password_confirmation'] ?? null,
            'contact_channel' => $channel,
            'contact_value' => $contact,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
            'contact_channel' => ['required', Rule::enum(ContactChannel::class)],
            'contact_value' => [
                'required', 'string', 'max:32',
                function (string $attribute, mixed $value, \Closure $fail) use ($channel): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if ($channel === ContactChannel::Telegram->value && ! preg_match('/^[A-Za-z0-9_]{5,32}$/D', $value)) {
                        $fail('Masukkan username Telegram yang valid (5–32 huruf, angka, atau garis bawah).');
                    }

                    if ($channel === ContactChannel::Whatsapp->value && ! preg_match('/^\+?[0-9]{8,15}$/D', $value)) {
                        $fail('Masukkan nomor WhatsApp yang valid (8–15 angka, boleh diawali +).');
                    }
                },
            ],
        ])->validate();

        try {
            $user = DB::transaction(function () use ($data): User {
                $user = new User;
                $user->forceFill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => UserRole::Client,
                    'status' => UserStatus::Active,
                    'contact_channel' => $data['contact_channel'],
                    'contact_value_enc' => $data['contact_value'],
                    'must_change_password' => false,
                ])->save();

                (new Wallet)->forceFill(['user_id' => $user->getKey()])->save();
                $this->audit($user, 'auth.registered', $user, ['role' => 'client', 'status' => 'active']);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Covers a simultaneous registration after the unique validator ran.
            throw ValidationException::withMessages(['email' => 'Email ini sudah digunakan.']);
        }

        $this->establishSession($user);

        return $user;
    }

    public function login(string $email, string $password): User
    {
        $email = $this->normalizeEmail($email);
        $ip = (string) request()->ip();
        $emailKey = 'auth:login:email-ip:'.hash('sha256', $email.'|'.$ip);
        $ipKey = 'auth:login:ip:'.hash('sha256', $ip);

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages(['email' => 'Tidak dapat masuk. Periksa data Anda atau coba lagi nanti.']);
        }

        $user = mb_strlen($email) <= 191 ? User::query()->where('email', $email)->first() : null;
        $validPassword = Hash::check($password, $user?->password ?? self::DUMMY_PASSWORD_HASH);

        if (! $user || ! $validPassword || $user->status !== UserStatus::Active || mb_strlen($password) > 128) {
            RateLimiter::hit($emailKey, 900);
            RateLimiter::hit($ipKey, 900);

            throw ValidationException::withMessages(['email' => 'Tidak dapat masuk. Periksa data Anda atau coba lagi nanti.']);
        }

        RateLimiter::clear($emailKey);
        // Keep the IP failure budget: a valid account must not reset an attacker's budget.
        $this->establishSession($user);

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    public function changePassword(string $current, string $password, string $confirmation): void
    {
        $user = $this->authenticatedUser();
        $key = 'auth:change-password:'.hash('sha256', $user->getKey().'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['current_password' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }

        $data = Validator::make([
            'current_password' => $current,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'current_password' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ])->validate();

        try {
            $updated = DB::transaction(function () use ($user, $data): User {
                $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $this->assertActive($locked);

                if (! Hash::check($data['current_password'], $locked->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Password saat ini tidak sesuai.']);
                }

                $locked->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
                $revoked = $this->revokeSessions($locked);
                $this->audit($locked, 'auth.password_changed', $locked, [
                    'must_change_password' => false,
                    'sessions_revoked' => $revoked,
                ]);

                return $locked;
            });
        } catch (ValidationException $exception) {
            // Persist the failure after rollback, including with the database limiter store.
            RateLimiter::hit($key, 900);

            throw $exception;
        }

        RateLimiter::clear($key);
        $this->establishSession($updated);
    }

    public function manualReset(User $client, string $reason, bool $contactChecked, bool $identityChecked): string
    {
        $actor = $this->authenticatedUser();
        Gate::authorize('resetPassword', $client);
        $this->assertRecentAdmin($actor);

        if ($client->role !== UserRole::Client) {
            throw new AuthorizationException('Reset manual hanya tersedia untuk akun client.');
        }

        $key = 'auth:manual-reset:'.hash('sha256', $actor->getKey().'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['reason' => 'Terlalu banyak permintaan reset. Coba lagi nanti.']);
        }

        RateLimiter::hit($key, 900);
        Validator::make([
            'reason' => trim($reason),
            'contact_checked' => $contactChecked,
            'identity_checked' => $identityChecked,
        ], [
            'reason' => ['required', 'string', 'max:500'],
            'contact_checked' => ['accepted'],
            'identity_checked' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($actor, $client): string {
            $admin = User::query()->lockForUpdate()->findOrFail($actor->getKey());
            $this->assertRecentAdmin($admin);
            $locked = User::query()->lockForUpdate()->findOrFail($client->getKey());

            if ($locked->role !== UserRole::Client) {
                throw new AuthorizationException('Reset manual hanya tersedia untuk akun client.');
            }

            if ($locked->contact_channel === null || ! is_string($locked->contact_value_enc) || trim($locked->contact_value_enc) === '') {
                throw ValidationException::withMessages(['contact_checked' => 'Kontak terdaftar belum tersedia. Reset tidak dapat diproses.']);
            }

            $temporary = Str::random(32);
            $locked->forceFill(['password' => $temporary, 'must_change_password' => true])->save();
            $revoked = $this->revokeSessions($locked);
            $this->audit($admin, 'auth.client_password_reset', $locked, [
                'must_change_password' => true,
                'contact_checked' => true,
                'identity_checked' => true,
                'reason_provided' => true,
                'sessions_revoked' => $revoked,
            ]);

            // Return only to the authorized private admin UI; never persist or log plaintext.
            return $temporary;
        });
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    private function establishSession(User $user): void
    {
        session()->forget('auth');
        session()->forget('password_hash_'.Auth::getDefaultDriver());
        Auth::login($user);
        session()->regenerate();
        session()->put('auth.password_confirmed_at', now()->timestamp);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Autentikasi diperlukan.');
        }

        $this->assertActive($user);

        return $user;
    }

    private function assertActive(User $user): void
    {
        if ($user->status !== UserStatus::Active) {
            throw new AuthorizationException('Akun tidak dapat digunakan.');
        }
    }

    private function assertRecentAdmin(User $user): void
    {
        if (! Access::hasRecentReauthentication($user)) {
            throw new AuthorizationException('Verifikasi ulang password dan TOTP diperlukan.');
        }
    }

    private function revokeSessions(User $user): int
    {
        return DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())->delete();
    }

    private function audit(User $actor, string $action, User $subject, array $summary): void
    {
        Audit::record($actor, $action, $subject, $summary);
    }
}
