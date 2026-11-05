<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class AuthHttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_first_admin_is_created_only_by_private_cli_input_and_bootstrap_cannot_overwrite_it(): void
    {
        $this->artisan('tg:create-admin')
            ->expectsQuestion('Nama admin', 'Test admin')
            ->expectsQuestion('Email admin', 'TEST-ADMIN@EXAMPLE.INVALID')
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'synthetic-admin-password')
            ->expectsQuestion('Ulangi kata sandi', 'synthetic-admin-password')
            ->assertSuccessful();
        $admin = User::sole();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame('test-admin@example.invalid', $admin->email);
        $this->assertNull($admin->totp_enabled_at);
        $this->assertTrue(Hash::check('synthetic-admin-password', $admin->password));
        $this->artisan('tg:create-admin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_auth_pages_and_real_livewire_update_responses_cannot_be_cached(): void
    {
        foreach (['login', 'register'] as $name) {
            $response = $this->get(route($name))->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        preg_match('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $update = $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => ['name' => 'A test name'], 'calls' => []]],
        ])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $update->headers->get('Cache-Control'));
    }

    public function test_recovery_and_temporary_password_results_are_private_single_read_responses(): void
    {
        $records = new DomainRecords;
        $admin = User::findOrFail($records->user([
            'role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'totp_enabled_at' => '2026-11-04 00:00:00.000000',
        ]));
        $client = $records->user();
        $proof = [
            'totp_user_id' => $admin->id, 'totp_verified_at' => now()->timestamp,
            'admin_last_activity' => now()->timestamp, 'reauthenticated_at' => now()->timestamp,
        ];
        $codes = array_map(fn ($i) => str_pad((string) $i, 32, 'A'), range(1, 10));
        $this->actingAs($admin)->withSession(['auth' => $proof + ['recovery_codes_once' => $codes]])
            ->get(route('admin.2fa.recovery'))->assertOk()->assertSee($codes[0]);
        $this->assertFalse(session()->has('auth.recovery_codes_once'));
        $this->get(route('admin.2fa.recovery'))->assertNotFound();

        $response = $this->withSession(['auth' => $proof + [
            'temporary_password_user_id' => $client, 'temporary_password_once' => 'synthetic-private-password',
        ]])->get(route('admin.clients.password-result'))->assertOk()->assertSee('synthetic-private-password');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse(session()->has('auth.temporary_password_once'));
        $this->get(route('admin.clients.password-result'))->assertNotFound();
    }

    public function test_web_logout_and_livewire_updates_reject_missing_csrf_outside_testing_bypass(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->post(route('logout'))->assertStatus(419);
            $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), ['components' => []])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
