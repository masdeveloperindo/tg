<?php

namespace Tests\Feature;

use App\Livewire\Auth\AdminClients;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Order;
use App\Models\TradingAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class AuthorizationTest extends TestCase
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
        $request = Request::create('/member', 'GET', server: ['REMOTE_ADDR' => '203.0.113.20']);
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

    public function test_clients_can_read_only_their_own_resources_and_verified_admins_can_read_both(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $admin = $this->admin();
        $resources = $this->ownedResources($owner);

        foreach ($resources as $resource) {
            $this->assertTrue(Gate::forUser($owner)->allows('view', $resource), $resource::class.' owner');
            $this->assertFalse(Gate::forUser($other)->allows('view', $resource), $resource::class.' other client');
            $this->assertFalse(Gate::forUser($admin)->allows('view', $resource), $resource::class.' password-only admin');
        }

        $this->verifyAdmin($admin);
        foreach ($resources as $resource) {
            $this->assertTrue(Gate::forUser($admin)->allows('view', $resource), $resource::class.' verified admin');
        }
    }

    #[DataProvider('ineligibleOwners')]
    public function test_disabled_or_forced_password_clients_lose_owner_permissions(array $attributes): void
    {
        $owner = $this->client($attributes);
        foreach ($this->ownedResources($owner) as $resource) {
            $this->assertFalse(Gate::forUser($owner)->allows('view', $resource), $resource::class);
        }
    }

    public static function ineligibleOwners(): array
    {
        return [
            'disabled client' => [['status' => 'disabled']],
            'must change password' => [['must_change_password' => true]],
        ];
    }

    public function test_client_lists_and_audit_logs_require_a_verified_admin(): void
    {
        $client = $this->client();
        $admin = $this->admin();
        $audit = ActivityLog::findOrFail($this->records->create('activity_logs', ['actor_id' => $client->id]));
        foreach ([$client, $admin] as $actor) {
            $this->assertFalse(Gate::forUser($actor)->allows('viewAny', User::class));
            $this->assertFalse(Gate::forUser($actor)->allows('view', $client));
            $this->assertFalse(Gate::forUser($actor)->allows('viewAny', ActivityLog::class));
            $this->assertFalse(Gate::forUser($actor)->allows('view', $audit));
        }
        $this->verifyAdmin($admin);
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $client));
        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', ActivityLog::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $audit));
    }

    #[DataProvider('downloadStates')]
    public function test_license_download_requires_an_active_unexpired_owner_license(string $status, int $secondsUntilExpiry, bool $allowed): void
    {
        $this->freezeTime();
        $owner = $this->client();
        $license = License::findOrFail($this->records->license([
            'user_id' => $owner->id,
            'status' => $status,
            'expires_at' => now()->addSeconds($secondsUntilExpiry)->format('Y-m-d H:i:s.u'),
        ]));
        $this->assertSame($allowed, Gate::forUser($owner)->allows('download', $license));
        $this->assertFalse(Gate::forUser($this->client())->allows('download', $license));
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        $this->assertFalse(Gate::forUser($admin)->allows('download', $license));
    }

    public static function downloadStates(): array
    {
        return [
            'active future' => ['active', 1, true],
            'expires exactly now' => ['active', 0, false],
            'expired' => ['active', -1, false],
            'suspended future' => ['suspended', 3600, false],
            'revoked future' => ['revoked', 3600, false],
        ];
    }

    public function test_guests_and_wrong_roles_cannot_enter_member_or_admin_pages(): void
    {
        $this->get(route('member'))->assertRedirect(route('login'));
        $this->get(route('admin'))->assertRedirect(route('login'));
        $this->actingAs($this->client())->get(route('admin'))->assertForbidden();
        $this->get(route('admin.clients'))->assertForbidden();
        $this->get(route('admin.2fa.setup'))->assertForbidden();
        $this->get(route('admin.2fa.challenge'))->assertForbidden();
        $this->actingAs($this->admin());
        session()->put('auth.password_confirmed_at', now()->timestamp);
        $this->get(route('member'))->assertForbidden();
    }

    public function test_disabled_accounts_are_logged_out_and_cannot_reach_member_data(): void
    {
        $this->actingAs($this->client(['status' => 'disabled']));
        session()->put('auth.reauthenticated_at', now()->timestamp);
        $this->get(route('member'))->assertForbidden();
        $this->assertGuest();
        $this->assertNull(session('auth.reauthenticated_at'));
    }

    public function test_forced_password_change_allows_only_the_change_form_and_logout(): void
    {
        $this->actingAs($this->client(['must_change_password' => true]));
        $this->get(route('member'))->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_password_alone_cannot_enter_panel_and_copied_verification_identity_is_rejected(): void
    {
        $admin = $this->admin();
        session()->put('auth.password_confirmed_at', now()->timestamp);
        $this->actingAs($admin)->get(route('admin'))->assertRedirect(route('admin.2fa.challenge'));
        $this->verifyAdmin($admin);
        session()->put('auth.totp_user_id', $this->admin()->id);
        $this->get(route('admin'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.reauthenticated_at'));
    }

    public function test_fake_verified_session_cannot_replace_persisted_admin_totp_enrollment(): void
    {
        $admin = User::findOrFail($this->records->user(['role' => 'admin']));
        $this->verifyAdmin($admin);
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->get(route('admin'))->assertRedirect(route('admin.2fa.setup'));
    }

    #[DataProvider('invalidAdminIdleTimes')]
    public function test_invalid_admin_idle_timestamp_revokes_the_entire_session(mixed $idle): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        session()->put('auth.admin_last_activity', is_int($idle) ? now()->timestamp + $idle : $idle);
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->get(route('admin'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull(session('auth.reauthenticated_at'));
    }

    public static function invalidAdminIdleTimes(): array
    {
        return [
            'missing idle' => [null],
            'future idle' => [1],
            'over thirty minutes' => [-1801],
            'malformed idle' => ['not-a-timestamp'],
            'numeric decimal idle' => ['123.5'],
        ];
    }

    public function test_exact_thirty_minute_idle_boundary_is_accepted_and_refreshed(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        session()->put('auth.admin_last_activity', now()->subSeconds(1800)->timestamp);
        $this->get(route('admin'))->assertOk();
        $this->assertSame(now()->timestamp, session('auth.admin_last_activity'));
    }

    #[DataProvider('expiredAdminEntryPoints')]
    public function test_expired_admin_sessions_cannot_bypass_timeout_through_setup_challenge_or_password_change(string $route, bool $verified): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $this->actingAs($admin);
        if ($verified) {
            $this->verifyAdmin($admin);
            session()->put('auth.admin_last_activity', now()->subSeconds(1801)->timestamp);
        } else {
            session()->put('auth.password_confirmed_at', now()->subSeconds(1801)->timestamp);
        }
        $this->get(route($route))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('auth.totp_user_id'));
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public static function expiredAdminEntryPoints(): array
    {
        return [
            'verified admin setup' => ['admin.2fa.setup', true],
            'verified admin challenge' => ['admin.2fa.challenge', true],
            'verified admin password change' => ['password.change', true],
            'first factor setup' => ['admin.2fa.setup', false],
            'first factor challenge' => ['admin.2fa.challenge', false],
            'first factor password change' => ['password.change', false],
        ];
    }

    public function test_fresh_password_only_admin_can_reach_challenge_at_thirty_minute_boundary(): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $this->actingAs($admin);
        session()->put('auth.password_confirmed_at', now()->subSeconds(1800)->timestamp);
        $this->get(route('admin.2fa.challenge'))->assertOk();
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('auth.totp_user_id'));
        $this->get(route('admin'))->assertRedirect(route('admin.2fa.challenge'));
    }

    #[DataProvider('invalidVerifiedTimes')]
    public function test_missing_malformed_or_future_totp_verified_marker_does_not_grant_admin_permissions(mixed $verified): void
    {
        $this->freezeTime();
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        session()->put('auth.totp_verified_at', $verified === 'future' ? now()->addSecond()->timestamp : $verified);
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($admin)->allows('resetPassword', $this->client()));
        $this->get(route('admin'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public static function invalidVerifiedTimes(): array
    {
        return [
            'missing verification' => [null],
            'malformed verification' => ['not-a-timestamp'],
            'future verification' => ['future'],
            'zero verification' => [0],
        ];
    }

    #[DataProvider('reauthenticationAges')]
    public function test_client_password_reset_requires_session_local_recent_reauthentication(mixed $age, bool $allowed): void
    {
        $this->freezeTime();
        $admin = $this->admin(['admin_last_reauth_at' => now()->format('Y-m-d H:i:s.u')]);
        $client = $this->client();
        $this->verifyAdmin($admin);
        session()->put('auth.reauthenticated_at', is_int($age) ? now()->timestamp - $age : $age);
        $this->assertSame($allowed, Gate::forUser($admin)->allows('resetPassword', $client));
        $this->assertFalse(Gate::forUser($client)->allows('resetPassword', $client));
        $this->assertFalse(Gate::forUser($admin)->allows('resetPassword', $admin));
        $this->assertFalse(Gate::forUser($admin)->allows('resetPassword', $this->admin()));
    }

    public static function reauthenticationAges(): array
    {
        return [
            'fresh local proof' => [0, true],
            'exact ten minutes' => [600, true],
            'over ten minutes' => [601, false],
            'future proof' => [-1, false],
            'DB timestamp alone' => [null, false],
            'malformed local proof' => ['not-a-timestamp', false],
        ];
    }

    public function test_client_cannot_forge_admin_permissions_by_copying_every_verification_session_key(): void
    {
        $client = $this->client();
        $this->verifyAdmin($client);
        $this->assertFalse(Gate::forUser($client)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($client)->allows('viewAny', ActivityLog::class));
        $this->assertFalse(Gate::forUser($client)->allows('resetPassword', $this->client()));
        $this->get(route('admin.clients'))->assertForbidden();
    }

    public function test_livewire_reset_action_reauthorizes_after_the_authenticated_actor_changes(): void
    {
        $admin = $this->admin();
        $target = $this->client();
        $other = $this->client();
        $originalHash = $target->getRawOriginal('password');
        $this->verifyAdmin($admin);
        $component = Livewire::test(AdminClients::class)->call('openReset', $target->id)->assertSet('targetId', $target->id);
        $this->actingAs($other);
        $component->call('resetPassword')->assertForbidden();
        $this->assertSame($originalHash, $target->fresh()->getRawOriginal('password'));
        $this->assertDatabaseMissing('activity_logs', ['action' => 'auth.client_password_reset']);
    }

    public function test_livewire_reset_action_rejects_stale_reauthentication_after_modal_open(): void
    {
        $admin = $this->admin();
        $target = $this->client();
        $originalHash = $target->getRawOriginal('password');
        $this->verifyAdmin($admin);
        $component = Livewire::test(AdminClients::class)
            ->call('openReset', $target->id)
            ->set('reason', 'Synthetic identity verification')
            ->set('contactChecked', true)
            ->set('identityChecked', true);
        session()->put('auth.reauthenticated_at', now()->subSeconds(601)->timestamp);
        $component->call('resetPassword')->assertForbidden();
        $this->assertSame($originalHash, $target->fresh()->getRawOriginal('password'));
        $this->assertFalse($target->fresh()->must_change_password);
    }

    public function test_livewire_reset_rejects_a_tampered_admin_target(): void
    {
        $admin = $this->admin();
        $this->verifyAdmin($admin);
        $otherAdmin = $this->admin();
        $originalHash = $otherAdmin->getRawOriginal('password');
        Livewire::test(AdminClients::class)->set('targetId', $otherAdmin->id)->assertForbidden();
        $this->assertSame($originalHash, $otherAdmin->fresh()->getRawOriginal('password'));
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'auth.client_password_reset')->count());
    }

    /** @return array<Model> */
    private function ownedResources(User $owner): array
    {
        return [
            Order::findOrFail($this->records->order(['user_id' => $owner->id])),
            License::findOrFail($this->records->license(['user_id' => $owner->id])),
            TradingAccount::findOrFail($this->records->tradingAccount(['user_id' => $owner->id])),
            Wallet::findOrFail($this->records->create('wallets', ['user_id' => $owner->id])),
            Withdrawal::findOrFail($this->records->withdrawal(['user_id' => $owner->id])),
        ];
    }

    private function client(array $attributes = []): User
    {
        return User::findOrFail($this->records->user($attributes));
    }

    private function admin(array $attributes = []): User
    {
        return User::findOrFail($this->records->user(array_replace([
            'role' => 'admin',
            'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'),
            'totp_enabled_at' => now()->format('Y-m-d H:i:s.u'),
        ], $attributes)));
    }

    private function verifyAdmin(User $admin): void
    {
        $this->actingAs($admin);
        session()->put([
            'auth.totp_user_id' => $admin->id,
            'auth.totp_verified_at' => now()->timestamp,
            'auth.admin_last_activity' => now()->timestamp,
            'auth.password_confirmed_at' => now()->timestamp,
            'auth.reauthenticated_at' => now()->timestamp,
        ]);
    }
}
