<?php

namespace Tests\Feature;

use App\Actions\Auth\Authentication;
use App\Enums\UserRole;
use App\Livewire\Auth\AdminClients;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->records = new DomainRecords;
        Cache::flush();
        $session = app('session')->driver();
        $session->start();
        $request = Request::create('/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.10']);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => Auth::user());
        app()->instance('request', $request);
        Mail::fake();
        Notification::fake();
    }

    public function test_registration_ignores_forged_privileges_encrypts_contact_creates_wallet_and_regenerates_session(): void
    {
        $before = session()->getId();
        session()->put('auth.totp_user_id', 999999);
        session()->put('auth.reauthenticated_at', now()->timestamp);
        $user = app(Authentication::class)->register($this->registration([
            'email' => '  REGISTERED@EXAMPLE.INVALID ', 'contact_value' => '@schema_client',
            'role' => 'admin', 'status' => 'disabled', 'must_change_password' => true,
            'totp_secret_enc' => 'forged-secret',
        ]));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertSame('active', $user->status->value);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->totp_secret_enc);
        $this->assertSame('registered@example.invalid', $user->email);
        $this->assertSame('schema_client', $user->contact_value_enc);
        $raw = DB::table('users')->find($user->id);
        $this->assertSame('schema_client', Crypt::decryptString($raw->contact_value_enc));
        $this->assertNotSame('schema_client', $raw->contact_value_enc);
        $this->assertTrue(Hash::check('synthetic-password-123', $raw->password));
        $this->assertSame('argon2id', password_get_info($raw->password)['algoName']);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id]);
        $this->assertSame(1, DB::table('wallets')->where('user_id', $user->id)->count());
        $this->assertNotSame($before, session()->getId());
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull(session('auth.reauthenticated_at'));
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $user->id, 'action' => 'auth.registered']);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_registration_accepts_whatsapp_without_email_verification_or_client_otp(): void
    {
        $user = app(Authentication::class)->register($this->registration([
            'contact_channel' => 'whatsapp', 'contact_value' => '+6281234567890',
        ]));
        $this->assertSame('+6281234567890', $user->contact_value_enc);
        $this->assertNull($user->totp_enabled_at);
        $this->get(route('member'))->assertOk();
    }

    #[DataProvider('invalidRegistrations')]
    public function test_invalid_registration_does_not_leave_a_user_wallet_or_audit_record(array $overrides, string $field): void
    {
        $this->validation(fn () => app(Authentication::class)->register($this->registration($overrides)), $field);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertGuest();
    }

    public static function invalidRegistrations(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'invalid email' => [['email' => 'not-an-email'], 'email'],
            'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            'different confirmation' => [['password_confirmation' => 'different-password'], 'password'],
            'missing contact' => [['contact_value' => ''], 'contact_value'],
            'unknown channel' => [['contact_channel' => 'telegram_bot'], 'contact_channel'],
            'invalid Telegram link' => [['contact_value' => 'https://t.me/invalid'], 'contact_value'],
            'invalid WhatsApp' => [['contact_channel' => 'whatsapp', 'contact_value' => 'not-a-phone'], 'contact_value'],
        ];
    }

    public function test_case_variants_cannot_register_a_second_account(): void
    {
        $this->records->user(['email' => 'registered@example.invalid']);
        $this->validation(fn () => app(Authentication::class)->register($this->registration(['email' => 'REGISTERED@EXAMPLE.INVALID'])), 'email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function test_registration_attempts_are_limited_per_ip_without_creating_partial_accounts(): void
    {
        $service = app(Authentication::class);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->validation(fn () => $service->register($this->registration(['name' => ''])), 'name');
        }
        $this->validation(fn () => $service->register($this->registration()), 'email');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('wallets', 0);
        request()->server->set('REMOTE_ADDR', '203.0.113.11');
        $this->assertAuthenticatedAs($service->register($this->registration()));
    }

    public function test_login_regenerates_session_and_does_not_reuse_old_admin_verification(): void
    {
        $id = $this->records->user(['email' => 'login@example.invalid']);
        $before = session()->getId();
        foreach (['totp_user_id', 'totp_verified_at', 'admin_last_activity', 'reauthenticated_at'] as $key) {
            session()->put('auth.'.$key, $key === 'totp_user_id' ? $id : now()->timestamp);
        }
        $user = app(Authentication::class)->login(' LOGIN@EXAMPLE.INVALID ', 'test-only-password');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull(session('auth.reauthenticated_at'));
        $this->assertSame(now()->timestamp, session('auth.password_confirmed_at'));
    }

    public function test_missing_wrong_password_and_disabled_account_return_the_same_generic_login_error(): void
    {
        $this->records->user(['email' => 'active@example.invalid']);
        $this->records->user(['email' => 'disabled@example.invalid', 'status' => 'disabled']);
        $missing = $this->validation(fn () => app(Authentication::class)->login('missing@example.invalid', 'test-only-password'), 'email');
        $wrong = $this->validation(fn () => app(Authentication::class)->login('active@example.invalid', 'wrong-password'), 'email');
        $disabled = $this->validation(fn () => app(Authentication::class)->login('disabled@example.invalid', 'test-only-password'), 'email');
        $this->assertSame($missing['email'], $wrong['email']);
        $this->assertSame($missing['email'], $disabled['email']);
        $this->assertGuest();
    }

    public function test_login_is_limited_to_five_failures_per_normalized_email_and_ip_for_fifteen_minutes(): void
    {
        $this->records->user(['email' => 'limited@example.invalid']);
        $service = app(Authentication::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->validation(fn () => $service->login(' LIMITED@EXAMPLE.INVALID ', 'wrong-password'), 'email');
        }
        $this->assertTrue(RateLimiter::tooManyAttempts('auth:login:email-ip:'.hash('sha256', 'limited@example.invalid|203.0.113.10'), 5));
        $this->validation(fn () => $service->login('limited@example.invalid', 'test-only-password'), 'email');
        $this->assertGuest();
        $this->travel(901)->seconds();
        $user = $service->login('limited@example.invalid', 'test-only-password');
        $this->assertAuthenticatedAs($user);
    }

    public function test_ip_limit_prevents_email_rotation_and_isolated_ips_can_still_login(): void
    {
        $this->records->user(['email' => 'ip-limited@example.invalid']);
        $service = app(Authentication::class);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->validation(fn () => $service->login('unknown-'.$attempt.'@example.invalid', 'wrong-password'), 'email');
        }
        $this->assertTrue(RateLimiter::tooManyAttempts('auth:login:ip:'.hash('sha256', '203.0.113.10'), 20));
        $this->validation(fn () => $service->login('ip-limited@example.invalid', 'test-only-password'), 'email');
        request()->server->set('REMOTE_ADDR', '203.0.113.11');
        $user = $service->login('ip-limited@example.invalid', 'test-only-password');
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_the_session_and_regenerates_csrf_token(): void
    {
        $user = User::findOrFail($this->records->user());
        $this->actingAs($user);
        session()->put('auth.totp_user_id', $user->id);
        $before = session()->getId();
        $token = csrf_token();
        app(Authentication::class)->logout();
        $this->assertGuest();
        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame($token, csrf_token());
        $this->assertNull(session('auth.totp_user_id'));
        $this->get(route('member'))->assertRedirect(route('login'));
    }

    public function test_client_can_change_password_only_with_the_current_password_and_old_sessions_are_revoked(): void
    {
        $user = User::findOrFail($this->records->user(['must_change_password' => true]));
        $this->actingAs($user);
        $this->oldSession($user->id, 'password-change-old');
        $this->validation(fn () => app(Authentication::class)->changePassword('wrong-current', 'new-synthetic-password', 'new-synthetic-password'), 'current_password');
        $this->assertTrue(Hash::check('test-only-password', $user->fresh()->password));
        $this->assertDatabaseHas('sessions', ['user_id' => $user->id]);
        $before = session()->getId();
        app(Authentication::class)->changePassword('test-only-password', 'new-synthetic-password', 'new-synthetic-password');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('new-synthetic-password', $user->fresh()->password));
        $this->assertNotSame($before, session()->getId());
        $this->assertSame(0, DB::table('sessions')->where('id', 'password-change-old')->count());
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $user->id, 'action' => 'auth.password_changed']);
    }

    public function test_manual_reset_requires_two_identity_checks_then_revokes_sessions_and_redacts_the_audit(): void
    {
        $admin = $this->verifiedAdmin();
        $client = User::findOrFail($this->records->user([
            'contact_channel' => 'telegram', 'contact_value_enc' => Crypt::encryptString('synthetic_contact'),
        ]));
        $this->oldSession($client->id, 'manual-reset-old-1');
        $this->oldSession($client->id, 'manual-reset-old-2');
        $service = app(Authentication::class);
        $this->validation(fn () => $service->manualReset($client, 'Test identity verification', false, true), 'contact_checked');
        $this->validation(fn () => $service->manualReset($client, 'Test identity verification', true, false), 'identity_checked');
        $this->assertFalse($client->fresh()->must_change_password);
        $this->assertDatabaseCount('sessions', 2);
        $temporary = $service->manualReset($client, 'Test identity verification', true, true);
        $this->assertGreaterThanOrEqual(16, strlen($temporary));
        $this->assertTrue(Hash::check($temporary, $client->fresh()->password));
        $this->assertFalse(Hash::check('test-only-password', $client->fresh()->password));
        $this->assertTrue($client->fresh()->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $client->id)->count());
        $audit = DB::table('activity_logs')->where('action', 'auth.client_password_reset')->sole();
        $this->assertSame($admin->id, (int) $audit->actor_id);
        $this->assertStringNotContainsString($temporary, $audit->summary_json);
        $this->assertStringNotContainsString('synthetic_contact', $audit->summary_json);
        $this->assertStringNotContainsString('test-only-password', $audit->summary_json);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_register_and_login_livewire_forms_use_the_real_service(): void
    {
        $registration = Livewire::test(Register::class);
        foreach ($this->registration() as $name => $value) {
            $registration->set($name, $value);
        }
        $registration->call('submit')->assertHasNoErrors()->assertSet('password', '')->assertSet('password_confirmation', '')->assertRedirect(route('member'));
        $user = User::where('email', 'registered@example.invalid')->sole();
        $this->assertAuthenticatedAs($user);
        app(Authentication::class)->logout();
        Livewire::test(Login::class)
            ->set('email', 'registered@example.invalid')
            ->set('password', 'synthetic-password-123')
            ->call('submit')->assertHasNoErrors()->assertSet('password', '')->assertRedirect(route('member'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_livewire_reset_displays_the_temporary_password_once_on_a_private_response(): void
    {
        $admin = $this->verifiedAdmin();
        $client = User::findOrFail($this->records->user([
            'contact_channel' => 'telegram', 'contact_value_enc' => Crypt::encryptString('verified_client_contact'),
        ]));
        app()->rebinding('request', function ($app, $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        $component = Livewire::test(AdminClients::class)
            ->call('openReset', $client->id)
            ->assertSee('verified_client_contact')
            ->set('reason', 'Synthetic verified identity')
            ->set('contactChecked', true)
            ->set('identityChecked', true)
            ->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('admin.clients.password-result'));
        $temporary = session('auth.temporary_password_once');
        $this->assertIsString($temporary);
        $this->assertStringNotContainsString($temporary, json_encode($component->snapshot, JSON_THROW_ON_ERROR));
        $this->assertTrue(Hash::check($temporary, $client->fresh()->password));
        $this->assertSame($client->id, session('auth.temporary_password_user_id'));
        $response = $this->get(route('admin.clients.password-result'))->assertOk()->assertSee($temporary);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertNull(session('auth.temporary_password_once'));
        $this->get(route('admin.clients.password-result'))->assertNotFound();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_reset_cannot_claim_contact_verification_for_an_account_without_a_registered_contact(): void
    {
        $this->verifiedAdmin();
        $client = User::findOrFail($this->records->user());
        $before = $client->password;
        $this->validation(fn () => app(Authentication::class)->manualReset($client, 'Synthetic identity check', true, true), 'contact_checked');
        $this->assertSame($before, $client->fresh()->password);
        $this->assertFalse($client->fresh()->must_change_password);
    }

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Authentication test', 'email' => 'registered@example.invalid',
            'password' => 'synthetic-password-123', 'password_confirmation' => 'synthetic-password-123',
            'contact_channel' => 'telegram', 'contact_value' => 'schema_client',
        ], $overrides);
    }

    private function validation(callable $operation, string $field): array
    {
        try {
            $operation();
            $this->fail('Invalid authentication operation unexpectedly succeeded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return $exception->errors();
        }
    }

    private function oldSession(int $userId, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '203.0.113.10', 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp]);
    }

    private function verifiedAdmin(): User
    {
        $user = User::findOrFail($this->records->user([
            'role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            'totp_enabled_at' => now()->format('Y-m-d H:i:s.u'),
        ]));
        $this->actingAs($user);
        session()->put([
            'auth.totp_user_id' => $user->id, 'auth.totp_verified_at' => now()->timestamp,
            'auth.admin_last_activity' => now()->timestamp, 'auth.password_confirmed_at' => now()->timestamp,
            'auth.reauthenticated_at' => now()->timestamp,
        ]);

        return $user;
    }
}
