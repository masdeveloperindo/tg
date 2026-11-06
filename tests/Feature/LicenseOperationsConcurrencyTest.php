<?php

namespace Tests\Feature;

use App\Actions\LicenseApi\Envelope;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\LicenseWorkflow;
use Tests\TestCase;

class LicenseOperationsConcurrencyTest extends TestCase
{
    use LicenseWorkflow;

    private function workers(string $mode, int $user, int $id, array $envs): array
    {
        $barrier = storage_path('framework/testing/license-barrier-'.Str::uuid());
        $workers = [];
        try {
            foreach ($envs as $extra) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/license-operations-worker.php'), $mode, (string) $user, (string) $id, $barrier], base_path(), [...$extra, 'APP_ENV' => 'testing', 'DB_DATABASE' => 'tg_test', 'DB_URL' => '', 'APP_KEY' => config('app.key')]);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $until = microtime(true) + 10;
            while (! str_contains($workers[0]->getOutput(), 'READY') || ! str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $until) {
                    $this->fail('Workers did not reach barrier.');
                } usleep(10000);
            }
            file_put_contents($barrier, 'go');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), 'Independent operation worker failed.');
                $lines = explode("\n", trim($worker->getOutput()));
                $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            @unlink($barrier);
        }
    }

    public function test_parallel_nonce_refund_and_account_requests_obey_database_invariants(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tg-test-race-key-');
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            $this->prepareWorkflow();
            $license = $this->issue();
            $admin = auth()->id();
            $pair = sodium_crypto_sign_keypair();
            chmod($path, 0600);
            file_put_contents($path, base64_encode(sodium_crypto_sign_secretkey($pair)));
            $payload = json_encode(['protocol' => 1, 'license_key' => $license->key_ciphertext, 'product_id' => $license->product_id, 'account_number' => $license->mt5_account, 'server' => $license->mt5_server, 'account_type' => 'demo', 'version' => '1.0.0', 'timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'nonce' => Envelope::encode(random_bytes(16))], JSON_THROW_ON_ERROR);
            $env = ['TG_TEST_PAYLOAD' => $payload, 'TG_LICENSE_ED25519_PRIVATE_KEY_PATH' => $path];
            $results = $this->workers('api', $this->buyer->id, $license->id, [$env, $env]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame([200, 409], $statuses);
            $this->assertSame(1, DB::table('api_nonces')->count());
            $this->assertSame(1, DB::table('license_activations')->count());
            $this->assertSame(1, DB::table('license_logs')->count());
            $amount = bcmul($license->issuedOrder->price_locked_idr, '0.60', 2);
            $results = $this->workers('refund', $admin, $license->issued_order_id, [['TG_TEST_AMOUNT' => $amount, 'TG_TEST_INTENT' => (string) Str::uuid()], ['TG_TEST_AMOUNT' => $amount, 'TG_TEST_INTENT' => (string) Str::uuid()]]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame([200, 422], $statuses);
            $this->assertSame(1, DB::table('refunds')->count());
            $this->assertSame('paid', $license->issuedOrder->fresh()->status->value);
            $results = $this->workers('account', $this->buyer->id, $license->id, [['TG_TEST_INTENT' => (string) Str::uuid()], ['TG_TEST_INTENT' => (string) Str::uuid()]]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame([200, 422], $statuses);
            $this->assertSame(1, DB::table('license_account_change_requests')->where('status', 'pending')->count());
            $this->assertSame($license->mt5_account, License::findOrFail($license->id)->mt5_account);
        } finally {
            @unlink($path);
            RefreshDatabaseState::$migrated = false;
        }
    }
}
