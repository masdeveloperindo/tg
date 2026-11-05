<?php

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminRecoveryCode;
use App\Models\User;
use App\Security\Access;
use App\Security\Totp;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AdminTwoFactor
{
    public function __construct(private readonly Totp $totp) {}

    public function beginSetup(User $user): string
    {
        $actor = $this->authorize($user);
        $this->assertPasswordFresh();
        $this->checkRateLimit($actor);
        if ($actor->totp_enabled_at !== null) {
            $this->invalid();
        }

        $pending = Session::get('auth.totp_setup_secret');
        $started = Session::get('auth.totp_setup_started_at');
        if ((string) Session::get('auth.totp_setup_user_id') === (string) $actor->getKey()
            && is_string($pending) && preg_match('/\A[A-Z2-7]{32}\z/', $pending)
            && is_int($started) && $this->isRecent($started, 600)) {
            return $pending;
        }

        $secret = $this->totp->generateSecret();
        Session::put([
            'auth.totp_setup_user_id' => $actor->getKey(),
            'auth.totp_setup_secret' => $secret,
            'auth.totp_setup_started_at' => now()->timestamp,
        ]);

        return $secret;
    }

    public function confirmSetup(User $user, string $otp): array
    {
        $actor = $this->authorize($user);
        $this->assertPasswordFresh();
        $this->attempt($actor);
        $secret = Session::get('auth.totp_setup_secret');
        $started = Session::get('auth.totp_setup_started_at');
        if ((string) Session::get('auth.totp_setup_user_id') !== (string) $actor->getKey()
            || ! is_string($secret) || ! is_int($started) || ! $this->isRecent($started, 600)) {
            $this->invalid();
        }

        [$actor, $codes] = $this->locked($actor, function (User $locked) use ($secret, $otp): array {
            if ($locked->totp_enabled_at !== null) {
                $this->invalid();
            }

            $this->consumeCounter($locked, $secret, trim($otp));
            $locked->totp_secret_enc = $secret;
            $locked->totp_enabled_at = now();
            $locked->admin_last_reauth_at = now();
            $locked->save();
            AdminRecoveryCode::query()->where('user_id', $locked->getKey())->delete();

            $codes = [];
            for ($i = 0; $i < 10; $i++) {
                $normalized = strtoupper(bin2hex(random_bytes(16)));
                AdminRecoveryCode::query()->forceCreate([
                    'user_id' => $locked->getKey(),
                    'code_hash' => hash('sha256', $normalized),
                ]);
                $codes[] = implode('-', str_split($normalized, 4));
            }

            return [$locked, $codes];
        });

        Session::forget(['auth.totp_setup_user_id', 'auth.totp_setup_secret', 'auth.totp_setup_started_at']);
        $this->markVerified($actor, true);
        RateLimiter::clear($this->rateKey($actor));

        return $codes;
    }

    public function challenge(User $user, string $value): void
    {
        $actor = $this->authorize($user);
        $this->attempt($actor);
        $passwordFresh = $this->passwordFresh();
        $actor = $this->locked($actor, function (User $locked) use ($value, $passwordFresh): User {
            $this->proveFactor($locked, $value);
            if ($passwordFresh) {
                $locked->admin_last_reauth_at = now();
                $locked->save();
            }

            return $locked;
        });

        $this->markVerified($actor, $passwordFresh);
        RateLimiter::clear($this->rateKey($actor));
    }

    public function reauth(User $user, string $password, string $otp): void
    {
        $actor = $this->authorize($user);
        $activity = Session::get('auth.admin_last_activity');
        $verified = Session::get('auth.totp_verified_at');
        if ((string) Session::get('auth.totp_user_id') !== (string) $actor->getKey()
            || ! is_int($verified) || $verified <= 0 || $verified > now()->timestamp
            || ! is_int($activity) || ! $this->isRecent($activity, 1800)) {
            throw new AuthorizationException('Akses tidak diizinkan.');
        }

        $this->attempt($actor);
        $actor = $this->locked($actor, function (User $locked) use ($password, $otp): User {
            if (! Hash::check($password, $locked->password)) {
                throw ValidationException::withMessages(['password' => 'Kredensial tidak valid.']);
            }

            $this->proveFactor($locked, $otp);
            $locked->admin_last_reauth_at = now();
            $locked->save();

            return $locked;
        });

        Session::put('auth.password_confirmed_at', now()->timestamp);
        $this->markVerified($actor, true);
        RateLimiter::clear($this->rateKey($actor));
    }

    private function authorize(User $user): User
    {
        if (Auth::id() === null || (string) Auth::id() !== (string) $user->getKey()) {
            throw new AuthorizationException('Akses tidak diizinkan.');
        }

        $actor = User::query()->find($user->getKey());
        if ($actor === null || $actor->role !== UserRole::Admin || $actor->status !== UserStatus::Active) {
            throw new AuthorizationException('Akses tidak diizinkan.');
        }

        if (! Access::adminSessionIsFresh($actor)) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            throw new AuthorizationException('Sesi telah berakhir. Silakan masuk kembali.');
        }

        return $actor;
    }

    private function locked(User $user, Closure $callback): mixed
    {
        try {
            return Cache::lock('totp:lock:'.$user->getKey(), 10)->block(3, function () use ($user, $callback): mixed {
                return DB::transaction(function () use ($user, $callback): mixed {
                    $locked = User::query()->lockForUpdate()->find($user->getKey());
                    if ($locked === null || (string) Auth::id() !== (string) $locked->getKey()
                        || $locked->role !== UserRole::Admin || $locked->status !== UserStatus::Active) {
                        throw new AuthorizationException('Akses tidak diizinkan.');
                    }

                    return $callback($locked);
                });
            });
        } catch (LockTimeoutException) {
            $this->invalid();
        }
    }

    private function proveFactor(User $user, string $value): void
    {
        if ($user->totp_enabled_at === null) {
            $this->invalid();
        }

        try {
            $secret = $user->totp_secret_enc;
        } catch (DecryptException) {
            $this->invalid();
        }
        if (! is_string($secret) || $secret === '') {
            $this->invalid();
        }

        $value = trim($value);
        if (preg_match('/\A[0-9]{6}\z/', $value)) {
            $this->consumeCounter($user, $secret, $value);

            return;
        }

        $normalized = strtoupper(str_replace('-', '', $value));
        if (! preg_match('/\A[A-F0-9]{32}\z/', $normalized)) {
            $this->invalid();
        }
        $recovery = AdminRecoveryCode::query()->where('user_id', $user->getKey())
            ->where('code_hash', hash('sha256', $normalized))->whereNull('used_at')
            ->lockForUpdate()->first();
        if ($recovery === null) {
            $this->invalid();
        }
        $recovery->used_at = now();
        $recovery->save();
    }

    private function consumeCounter(User $user, string $secret, string $value): void
    {
        try {
            $counter = $this->totp->matchingCounter($secret, $value, now()->timestamp);
        } catch (InvalidArgumentException) {
            $this->invalid();
        }
        $key = 'totp:counter:'.$user->getKey();
        if ($counter === null || $counter <= (int) Cache::get($key, -1)) {
            $this->invalid();
        }
        if (! Cache::put($key, $counter, 120)) {
            $this->invalid();
        }
    }

    private function markVerified(User $user, bool $fresh): void
    {
        Session::regenerate(true);
        Session::put([
            'auth.totp_user_id' => $user->getKey(),
            'auth.totp_verified_at' => now()->timestamp,
            'auth.admin_last_activity' => now()->timestamp,
        ]);
        if ($fresh) {
            Session::put('auth.reauthenticated_at', now()->timestamp);
        } else {
            Session::forget('auth.reauthenticated_at');
        }
        Auth::setUser($user);
    }

    private function assertPasswordFresh(): void
    {
        if (! $this->passwordFresh()) {
            throw ValidationException::withMessages(['password' => 'Konfirmasikan kembali password Anda.']);
        }
    }

    private function passwordFresh(): bool
    {
        $confirmed = Session::get('auth.password_confirmed_at');

        return is_int($confirmed) && $this->isRecent($confirmed, 600);
    }

    private function isRecent(int $timestamp, int $seconds): bool
    {
        return $timestamp <= now()->timestamp && $timestamp >= now()->timestamp - $seconds;
    }

    private function checkRateLimit(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->rateKey($user), 5)) {
            throw ValidationException::withMessages(['otp' => 'Terlalu banyak percobaan. Coba lagi nanti.']);
        }
    }

    private function attempt(User $user): void
    {
        $this->checkRateLimit($user);
        RateLimiter::hit($this->rateKey($user), 900);
    }

    private function rateKey(User $user): string
    {
        return 'auth:totp:'.$user->getKey().':'.hash('sha256', request()->ip() ?? 'unknown');
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['otp' => 'Kode verifikasi tidak valid.']);
    }
}
