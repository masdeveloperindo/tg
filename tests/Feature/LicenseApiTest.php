<?php

namespace Tests\Feature;

use App\Actions\LicenseApi\Envelope;
use App\Actions\Licenses\ChangeAccount;
use App\Models\License;
use App\Models\LicenseLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\LicenseWorkflow;
use Tests\TestCase;

class LicenseApiTest extends TestCase
{
    use LicenseWorkflow, RefreshDatabase;

    private string $secretPath;

    private string $publicKey;

    private License $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareWorkflow();
        $this->license = $this->issue();
        $pair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($pair);
        $this->secretPath = tempnam(sys_get_temp_dir(), 'tg-test-sign-');
        chmod($this->secretPath, 0600);
        file_put_contents($this->secretPath, base64_encode(sodium_crypto_sign_secretkey($pair)));
        config(['app.url' => 'https://example.invalid', 'tradergaruda.license_private_key_path' => $this->secretPath, 'tradergaruda.license_signing_kid' => 'tg-test-only']);
    }

    protected function tearDown(): void
    {
        if (isset($this->secretPath)) {
            @unlink($this->secretPath);
        }
        parent::tearDown();
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['protocol' => 1, 'license_key' => strtolower($this->license->key_ciphertext), 'product_id' => $this->license->product_id, 'account_number' => $this->license->mt5_account, 'server' => $this->license->mt5_server, 'account_type' => 'demo', 'version' => '1.0.0', 'timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'nonce' => Envelope::encode(random_bytes(16))], $changes);
    }

    private function verdict(array $input, string $expected): array
    {
        $response = $this->postJson('/api/v1/licenses/verify', $input)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $json = $response->json();
        $bytes = base64_decode(strtr($json['payload_b64'], '-_', '+/'));
        $signature = base64_decode(strtr($json['signature'], '-_', '+/'));
        $this->assertTrue(sodium_crypto_sign_verify_detached($signature, $bytes, $this->publicKey));
        $this->assertFalse(sodium_crypto_sign_verify_detached($signature, $bytes.' ', $this->publicKey));
        $payload = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($expected, $payload['status']);
        $this->assertSame($input['nonce'], $payload['request_nonce']);
        $this->assertSame($json['kid'], $payload['kid']);
        $this->assertSame($response->headers->get('X-Request-ID'), $payload['request_id']);
        $this->assertStringNotContainsString($input['license_key'], $response->getContent());

        return $payload;
    }

    public function test_real_ed25519_signature_identity_expiry_and_redacted_logs(): void
    {
        $input = $this->payload();
        $payload = $this->verdict($input, 'valid');
        $this->assertSame($this->license->expires_at->utc()->format('Y-m-d\TH:i:s\Z'), $payload['expires_at']);
        $this->assertSame('1.1.0', $payload['latest_version']);
        $this->assertDatabaseCount('license_activations', 1);
        $this->assertSame(64, strlen(DB::table('api_nonces')->value('nonce_sha256')));
        $raw = LicenseLog::first()->getRawOriginal();
        $this->assertNull($raw['ip_enc']);
        $this->assertSame('****5678', $raw['account_number_masked']);
        $all = json_encode($raw);
        $this->assertStringNotContainsString($input['nonce'], $all);
        $this->assertStringNotContainsString($input['license_key'], $all);
        $this->assertStringNotContainsString('127.0.0.1', $all);
    }

    public function test_nonce_replay_is_atomic_durable_and_expires_after_ten_minutes(): void
    {
        $this->freezeTime();
        $input = $this->payload();
        $this->verdict($input, 'valid');
        $this->postJson('/api/v1/licenses/verify', $input)->assertStatus(409)->assertJsonPath('code', 'replay_detected');
        $this->assertSame(1, DB::table('api_nonces')->count());
        $this->assertSame(1, LicenseLog::count());
        $this->assertSame(600, (int) CarbonImmutable::parse(DB::table('api_nonces')->value('created_at'))->diffInSeconds(CarbonImmutable::parse(DB::table('api_nonces')->value('expires_at')), false));
    }

    public function test_all_business_denials_are_signed_and_do_not_create_an_activation(): void
    {
        foreach ([['account_number' => '9999'], ['account_type' => 'real']] as $change) {
            $this->verdict($this->payload($change), 'account_mismatch');
        }
        $this->verdict($this->payload(['server' => 'Different']), 'server_mismatch');
        $this->verdict($this->payload(['version' => '0.9.0']), 'update_required');
        $this->verdict($this->payload(['product_id' => $this->license->product_id + 1]), 'invalid');
        $this->verdict($this->payload(['license_key' => str_repeat('FFFF-', 7).'FFFF']), 'invalid');
        foreach (['suspended', 'revoked'] as $status) {
            $this->license->forceFill(['status' => $status])->save();
            $this->verdict($this->payload(), $status);
        }
        $this->license->forceFill(['status' => 'active', 'expires_at' => now()])->save();
        $this->verdict($this->payload(), 'expired');
        $this->assertDatabaseCount('license_activations', 0);
    }

    public function test_disabled_owner_and_indicator_without_poc_fail_closed(): void
    {
        $this->buyer->forceFill(['status' => 'disabled'])->save();
        $this->verdict($this->payload(), 'invalid');
        $this->buyer->forceFill(['status' => 'active'])->save();
        $this->license->product->forceFill(['type' => 'indicator'])->save();
        $this->verdict($this->payload(), 'invalid');
    }

    public function test_request_validation_limits_and_time_skew_do_not_echo_secrets(): void
    {
        foreach ([['account_number' => 12345678], ['nonce' => str_repeat('a', 21)], ['nonce' => 'aaaaaaaaaaaaaaaaaaaaab'], ['timestamp' => now()->subSeconds(301)->utc()->format('Y-m-d\TH:i:s\Z')], ['version' => 'bad'], ['protocol' => 2], ['extra' => 'secret']] as $change) {
            $input = $this->payload($change);
            $response = $this->postJson('/api/v1/licenses/verify', $input)->assertStatus(422)->assertJsonPath('code', 'invalid_request');
            $this->assertStringNotContainsString($input['license_key'], $response->getContent());
        }
        $this->call('POST', '/api/v1/licenses/verify', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('x', 4097))->assertStatus(413);
        $this->post('/api/v1/licenses/verify', $this->payload())->assertStatus(422);
        $this->assertDatabaseCount('api_nonces', 0);
    }

    public function test_rate_limits_key_and_ip_independently_without_revealing_identity(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/v1/licenses/verify', $this->payload(['protocol' => 2]))->assertStatus(422);
        }
        $this->postJson('/api/v1/licenses/verify', $this->payload())->assertStatus(429)->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
        for ($i = 61; $i < 120; $i++) {
            $this->postJson('/api/v1/licenses/verify', $this->payload(['license_key' => strtoupper(bin2hex(random_bytes(20)))]))->assertStatus(422);
        }
        $this->postJson('/api/v1/licenses/verify', $this->payload(['license_key' => 'another']))->assertStatus(429);
    }

    public function test_missing_or_insecure_signing_key_is_unavailable_never_unsigned_valid(): void
    {
        chmod($this->secretPath, 0644);
        clearstatcache();
        $this->postJson('/api/v1/licenses/verify', $this->payload())->assertStatus(503)->assertJsonMissingPath('payload_b64');
        chmod($this->secretPath, 0600);
        config(['tradergaruda.license_private_key_path' => '/missing-key']);
        $this->postJson('/api/v1/licenses/verify', $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('license_activations', 0);
        $this->assertDatabaseCount('license_logs', 0);
        $this->postJson('/api/v1/licenses/deactivate', $this->payload())->assertNotFound();
    }

    public function test_nonce_cleanup_preserves_live_replay_window_and_defaults_to_dry_run(): void
    {
        $this->freezeTime();
        DB::table('api_nonces')->insert([
            ['nonce_sha256' => hash('sha256', 'expired'), 'expires_at' => now()->subSecond(), 'created_at' => now()->subMinutes(11)],
            ['nonce_sha256' => hash('sha256', 'live'), 'expires_at' => now()->addSecond(), 'created_at' => now()->subMinutes(9)],
        ]);
        $this->artisan('tg:prune-api-nonces')->assertSuccessful();
        $this->assertSame(2, DB::table('api_nonces')->count());
        $this->artisan('tg:prune-api-nonces', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('api_nonces')->count());
        $this->assertSame(hash('sha256', 'live'), DB::table('api_nonces')->value('nonce_sha256'));
    }

    public function test_approved_account_move_denies_old_terminal_and_allows_new_terminal_with_same_key(): void
    {
        $this->actingAs($this->buyer);
        $service = app(ChangeAccount::class);
        $change = $service->request($this->license->id, '87654321', 'New-Server', (string) Str::uuid());
        $this->admin();
        $service->decide($this->license->id, $change->id, 'approved', 'Synthetic identity checked');
        $this->verdict($this->payload(), 'account_mismatch');
        $this->verdict($this->payload(['account_number' => '87654321', 'server' => 'New-Server']), 'valid');
    }
}
