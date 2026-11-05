<?php

namespace Tests\Feature;

use App\Actions\Auth\AdminTwoFactor;
use App\Actions\Auth\Authentication;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Auth\TwoFactorSetup;
use App\Models\User;
use App\Security\Totp;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00', 'UTC'));
        $this->records = new DomainRecords;
        Cache::flush();
        $session = app('session')->driver();
        $session->start();
        $request = Request::create('/admin/2fa/challenge', 'POST', server: ['REMOTE_ADDR' => '203.0.113.20']);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());
        app()->instance('request', $request);
        // Livewire's component test harness skips StartSession middleware.
        app()->rebinding('request', static function ($app, Request $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
    }

    #[DataProvider('rfc6238Vectors')]
    public function test_totp_matches_the_official_rfc6238_sha1_vectors(int $timestamp, string $expected): void
    {
        $totp = app(Totp::class);
        $this->assertSame($expected, $totp->code(self::SECRET, $timestamp, 8));
        $this->assertSame(substr($expected, -6), $totp->code(self::SECRET, $timestamp));
    }

    public static function rfc6238Vectors(): array
    {
        return [
            [59, '94287082'],
            [1111111109, '07081804'],
            [1111111111, '14050471'],
            [1234567890, '89005924'],
            [2000000000, '69279037'],
            [20000000000, '65353130'],
        ];
    }

    public function test_totp_accepts_only_the_current_and_adjacent_thirty_second_counters(): void
    {
        $totp = app(Totp::class);
        $timestamp = 1234567890;
        foreach ([-1, 0, 1] as $offset) {
            $code = $totp->code(self::SECRET, $timestamp + 30 * $offset);
            $this->assertSame(intdiv($timestamp, 30) + $offset, $totp->matchingCounter(self::SECRET, $code, $timestamp));
        }
        foreach ([-2, 2] as $offset) {
            $this->assertNull($totp->matchingCounter(self::SECRET, $totp->code(self::SECRET, $timestamp + 30 * $offset), $timestamp));
        }
        foreach (['', '12345', '1234567', '123 45', '１２３４５６', 'abcdef'] as $invalid) {
            $this->assertNull($totp->matchingCounter(self::SECRET, $invalid, $timestamp));
        }
        $this->assertNull($totp->matchingCounter(self::SECRET, '123456', -1));
    }

    #[DataProvider('invalidTotpParameters')]
    public function test_totp_rejects_invalid_secret_timestamp_and_digit_parameters(string $secret, int $timestamp, int $digits): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(Totp::class)->code($secret, $timestamp, $digits);
    }

    public static function invalidTotpParameters(): array
    {
        return [
            'empty secret' => ['', 59, 6],
            'invalid alphabet' => ['INVALID!SECRET', 59, 6],
            'empty decoded secret' => ['A', 59, 6],
            'nonzero unused bits' => ['AB', 59, 6],
            'negative time' => [self::SECRET, -1, 6],
            'too few digits' => [self::SECRET, 59, 5],
            'too many digits' => [self::SECRET, 59, 9],
        ];
    }

    public function test_generated_secrets_have_160_bits_and_are_distinct(): void
    {
        $first = app(Totp::class)->generateSecret();
        $second = app(Totp::class)->generateSecret();
        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $first);
        $this->assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_setup_enables_only_after_a_valid_code_and_stores_only_encrypted_secret_and_hashed_recovery_codes(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactor::class);
        $secret = $service->beginSetup($admin);
        $this->assertSame($secret, $service->beginSetup($admin));
        $this->assertNull($admin->fresh()->totp_enabled_at);
        $this->assertNull($admin->fresh()->totp_secret_enc);
        $this->validation(fn () => $service->confirmSetup($admin, 'not-an-otp'), 'otp');
        $this->assertNull($admin->fresh()->totp_enabled_at);
        $this->assertDatabaseCount('admin_recovery_codes', 0);
        $before = session()->getId();
        $codes = $service->confirmSetup($admin, $this->code($secret));
        $this->assertCount(10, $codes);
        $this->assertCount(10, array_unique($codes));
        $raw = DB::table('users')->find($admin->id);
        $this->assertNotSame($secret, $raw->totp_secret_enc);
        $this->assertSame($secret, Crypt::decryptString($raw->totp_secret_enc));
        $this->assertNotNull($raw->totp_enabled_at);
        $this->assertNotNull($raw->admin_last_reauth_at);
        $this->assertArrayNotHasKey('totp_secret_enc', $admin->fresh()->toArray());
        $stored = DB::table('admin_recovery_codes')->where('user_id', $admin->id)->get();
        $this->assertCount(10, $stored);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/\A[A-F0-9]{4}(?:-[A-F0-9]{4}){7}\z/', $code);
            $hash = hash('sha256', str_replace('-', '', $code));
            $this->assertTrue($stored->contains('code_hash', $hash));
            $this->assertStringNotContainsString($code, $stored->toJson());
            $this->assertStringNotContainsString(str_replace('-', '', $code), $stored->toJson());
        }
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        $this->assertSame(now()->timestamp, session('auth.totp_verified_at'));
        $this->assertSame(now()->timestamp, session('auth.reauthenticated_at'));
        $this->assertNotSame($before, session()->getId());
        $this->assertNull(session('auth.totp_setup_secret'));
        $this->assertNull(session('auth.totp_setup_user_id'));
        $this->assertNull(session('auth.totp_setup_started_at'));
        $this->validation(fn () => $service->confirmSetup($admin, $this->code($secret)), 'otp');
        $this->assertDatabaseCount('admin_recovery_codes', 10);
    }

    #[DataProvider('invalidPasswordConfirmationTimes')]
    public function test_setup_requires_recent_past_password_confirmation(mixed $confirmation): void
    {
        $admin = $this->admin();
        session()->put('auth.password_confirmed_at', is_int($confirmation) ? now()->timestamp + $confirmation : $confirmation);
        $this->validation(fn () => app(AdminTwoFactor::class)->beginSetup($admin), 'password');
        $this->assertNull(session('auth.totp_setup_secret'));
        $this->assertNull($admin->fresh()->totp_enabled_at);
    }

    public static function invalidPasswordConfirmationTimes(): array
    {
        return ['over ten minutes' => [-601], 'first-factor boundary' => [-1800]];
    }

    public function test_expired_setup_cannot_be_confirmed_even_with_a_newly_confirmed_password(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactor::class);
        $secret = $service->beginSetup($admin);
        $this->travel(601)->seconds();
        session()->put('auth.password_confirmed_at', now()->timestamp);
        $this->validation(fn () => $service->confirmSetup($admin, $this->code($secret)), 'otp');
        $this->assertNull($admin->fresh()->totp_enabled_at);
        $this->assertDatabaseCount('admin_recovery_codes', 0);
    }

    public function test_setup_and_challenge_cannot_be_performed_for_another_admin_or_a_client(): void
    {
        $current = $this->admin();
        $other = User::findOrFail($this->records->user(['role' => 'admin']));
        $client = User::findOrFail($this->records->user());
        foreach ([$other, $client] as $target) {
            $this->unauthorized(fn () => app(AdminTwoFactor::class)->beginSetup($target));
            $this->unauthorized(fn () => app(AdminTwoFactor::class)->challenge($target, $this->code()));
        }
        DB::table('users')->where('id', $current->id)->update(['status' => 'disabled']);
        $this->unauthorized(fn () => app(AdminTwoFactor::class)->beginSetup($current));
        $this->assertDatabaseCount('admin_recovery_codes', 0);
    }

    public function test_admin_password_login_alone_cannot_open_the_admin_area(): void
    {
        $admin = $this->admin(true);
        app(Authentication::class)->logout();
        app(Authentication::class)->login($admin->email, 'test-only-password');
        $this->assertNull(session('auth.totp_user_id'));
        $this->get(route('admin'))->assertRedirect(route('admin.2fa.challenge'));
    }

    public function test_a_totp_counter_is_consumed_once_across_sessions_and_the_next_counter_can_authenticate(): void
    {
        $admin = $this->admin(true);
        $service = app(AdminTwoFactor::class);
        $code = $this->code();
        $service->challenge($admin, $code);
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        $this->assertSame(now()->timestamp, session('auth.reauthenticated_at'));
        session()->invalidate();
        $this->actingAs($admin);
        session()->put('auth.password_confirmed_at', now()->timestamp);
        $this->validation(fn () => $service->challenge($admin, $code), 'otp');
        $this->assertNull(session('auth.totp_user_id'));
        $this->travel(30)->seconds();
        $before = session()->getId();
        $service->challenge($admin, $this->code());
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        $this->assertSame(now()->timestamp, session('auth.admin_last_activity'));
        $this->assertNotSame($before, session()->getId());
        $this->get(route('admin'))->assertOk();
    }

    public function test_each_recovery_code_is_single_use_and_belongs_only_to_its_admin(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactor::class);
        $codes = $service->confirmSetup($admin, $this->code($service->beginSetup($admin)));
        $other = $this->admin(true);
        $this->validation(fn () => $service->challenge($other, $codes[0]), 'otp');
        $this->actingAs($admin);
        session()->forget('auth.totp_user_id');
        $service->challenge($admin, $codes[0]);
        $hash = hash('sha256', str_replace('-', '', $codes[0]));
        $this->assertNotNull(DB::table('admin_recovery_codes')->where('code_hash', $hash)->value('used_at'));
        session()->invalidate();
        $this->actingAs($admin);
        session()->put('auth.password_confirmed_at', now()->timestamp);
        $this->validation(fn () => $service->challenge($admin, $codes[0]), 'otp');
        $this->assertNull(session('auth.totp_user_id'));
        $service->challenge($admin, $codes[1]);
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        $this->assertDatabaseCount('admin_recovery_codes', 10);
        $this->assertSame(2, DB::table('admin_recovery_codes')->whereNotNull('used_at')->count());
    }

    public function test_challenge_limits_five_failures_and_expired_password_confirmation_does_not_grant_sensitive_reauth(): void
    {
        $admin = $this->admin(true);
        $service = app(AdminTwoFactor::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->validation(fn () => $service->challenge($admin, 'invalid'), 'otp');
        }
        $key = 'auth:totp:'.$admin->id.':'.hash('sha256', '203.0.113.20');
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->validation(fn () => $service->challenge($admin, $this->code()), 'otp');
        $this->assertNull(session('auth.totp_user_id'));
        $this->travel(901)->seconds();
        $service->challenge($admin, $this->code());
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        $this->assertNull(session('auth.reauthenticated_at'));
        $this->assertNull($admin->fresh()->admin_last_reauth_at);
    }

    public function test_an_expired_verified_admin_session_cannot_be_revived_with_only_a_new_otp(): void
    {
        $admin = $this->admin(true);
        $service = app(AdminTwoFactor::class);
        $service->challenge($admin, $this->code());
        $lastReauth = $admin->fresh()->getRawOriginal('admin_last_reauth_at');
        $this->travel(1801)->seconds();
        $this->unauthorized(fn () => $service->challenge($admin, $this->code()));
        $this->assertGuest();
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull(session('auth.reauthenticated_at'));
        $this->assertSame($lastReauth, $admin->fresh()->getRawOriginal('admin_last_reauth_at'));
    }

    #[DataProvider('invalidFirstFactorTimes')]
    public function test_challenge_requires_a_recent_password_login_when_not_yet_verified(mixed $confirmation): void
    {
        $admin = $this->admin(true);
        session()->put('auth.password_confirmed_at', is_int($confirmation) ? now()->timestamp + $confirmation : $confirmation);
        $this->unauthorized(fn () => app(AdminTwoFactor::class)->challenge($admin, $this->code()));
        $this->assertGuest();
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull($admin->fresh()->admin_last_reauth_at);
    }

    public static function invalidFirstFactorTimes(): array
    {
        return ['missing' => [null], 'future' => [1], 'older than thirty minutes' => [-1801], 'malformed' => ['not-a-timestamp']];
    }

    public function test_reauthentication_requires_both_password_and_an_unused_factor_and_updates_only_this_session(): void
    {
        $admin = $this->admin(true);
        $service = app(AdminTwoFactor::class);
        $service->challenge($admin, $this->code());
        $this->travel(30)->seconds();
        $code = $this->code();
        session()->forget('auth.reauthenticated_at');
        $this->validation(fn () => $service->reauth($admin, 'wrong-password', $code), 'password');
        $this->assertNull(session('auth.reauthenticated_at'));
        $this->validation(fn () => $service->reauth($admin, 'test-only-password', 'invalid'), 'otp');
        $this->assertNull(session('auth.reauthenticated_at'));
        $before = session()->getId();
        $service->reauth($admin, 'test-only-password', $code);
        $this->assertSame(now()->timestamp, session('auth.reauthenticated_at'));
        $this->assertSame(now()->timestamp, session('auth.password_confirmed_at'));
        $this->assertSame(now()->timestamp, $admin->fresh()->admin_last_reauth_at->timestamp);
        $this->assertNotSame($before, session()->getId());
        $this->validation(fn () => $service->reauth($admin, 'test-only-password', $code), 'otp');
        session()->invalidate();
        $this->actingAs($admin);
        $this->unauthorized(fn () => $service->reauth($admin, 'test-only-password', $this->code()));
    }

    public function test_challenge_livewire_verifies_the_admin_and_recovery_codes_are_shown_only_once(): void
    {
        $admin = $this->admin();
        $service = app(AdminTwoFactor::class);
        $codes = $service->confirmSetup($admin, $this->code($service->beginSetup($admin)));
        session()->forget(['auth.totp_user_id', 'auth.totp_verified_at', 'auth.admin_last_activity', 'auth.reauthenticated_at']);
        Livewire::test(TwoFactorChallenge::class)
            ->set('otp', $codes[0])->call('submit')->assertHasNoErrors()->assertSet('otp', '')->assertRedirect(route('admin'));
        $this->assertSame($admin->id, session('auth.totp_user_id'));
        session()->put('auth.recovery_codes_once', $codes);
        $response = $this->get(route('admin.2fa.recovery'))->assertOk();
        foreach ($codes as $code) {
            $response->assertSee($code);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('admin.2fa.recovery'))->assertNotFound();
        $this->assertNull(session('auth.recovery_codes_once'));
    }

    public function test_setup_livewire_keeps_the_secret_and_recovery_codes_out_of_component_snapshots(): void
    {
        $this->admin();
        $component = Livewire::test(TwoFactorSetup::class);
        $secret = session('auth.totp_setup_secret');
        $this->assertIsString($secret);
        $component->assertSee($secret);
        $this->assertStringNotContainsString($secret, json_encode($component->snapshot, JSON_THROW_ON_ERROR));
        $component->set('otp', $this->code($secret))->call('submit')
            ->assertHasNoErrors()->assertSet('otp', '')->assertRedirect(route('admin.2fa.recovery'));
        $codes = session('auth.recovery_codes_once');
        $this->assertCount(10, $codes);
        $snapshot = json_encode($component->snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($secret, $snapshot);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $snapshot);
        }
    }

    private function admin(bool $enabled = false): User
    {
        $attributes = ['role' => 'admin'];
        if ($enabled) {
            $attributes += ['totp_secret_enc' => Crypt::encryptString(self::SECRET), 'totp_enabled_at' => now()->format('Y-m-d H:i:s.u')];
        }
        $admin = User::findOrFail($this->records->user($attributes));
        $this->actingAs($admin);
        session()->forget('auth');
        session()->put('auth.password_confirmed_at', now()->timestamp);

        return $admin;
    }

    private function code(string $secret = self::SECRET): string
    {
        return app(Totp::class)->code($secret, now()->timestamp);
    }

    private function validation(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid second-factor operation unexpectedly succeeded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    private function unauthorized(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Unauthorized second-factor operation unexpectedly succeeded.');
        } catch (AuthorizationException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }
}
