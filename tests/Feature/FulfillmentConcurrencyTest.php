<?php

namespace Tests\Feature;

use App\Actions\Orders\Checkout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Support\CheckoutRecords as Fixtures;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class FulfillmentConcurrencyTest extends TestCase
{
    public function test_two_independent_admin_workers_confirm_once_and_issue_only_one_license(): void
    {
        $barrier = storage_path('framework/testing/checkout-barrier-'.bin2hex(random_bytes(8)));
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            Storage::fake('local');
            Fixtures::configure();
            config(['tradergaruda.license_sales_enabled' => true]);
            $user = User::findOrFail((new DomainRecords)->user());
            $this->actingAs($user);
            $session = app('session')->driver();
            $session->start();
            request()->setLaravelSession($session);
            $product = Fixtures::product();
            $quote = app(Checkout::class)->quote($product);
            $order = app(Checkout::class)->create($quote->id, Fixtures::input());
            $admin = User::findOrFail((new DomainRecords)->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
            $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'tg_test', 'DB_URL' => '', 'APP_KEY' => config('app.key'), 'TG_PRIVATE_STORAGE_PATH' => Storage::disk('local')->path('')];
            $workers = [];
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/fulfillment-worker.php'), (string) $admin->id, (string) $order->id, $barrier], base_path(), $env);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 10;
            while (! str_contains($workers[0]->getOutput(), 'READY') || ! str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Independent workers did not become ready.');
                }usleep(10000);
            }
            file_put_contents($barrier, 'go');
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), 'Independent checkout worker failed.');
                $lines = explode("\n", trim($worker->getOutput()));
                $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertSame($results[0]['license_id'], $results[1]['license_id']);
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
            $this->assertSame(1, DB::table('orders')->count());
            $this->assertSame(1, DB::table('activity_logs')->where('action', 'order.payment_confirmed')->count());
            $this->assertSame(1, DB::table('licenses')->count());
            $this->assertSame('paid', $order->fresh()->status->value);
        } finally {
            foreach ($workers ?? [] as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }RefreshDatabaseState::$migrated = false;
        }
    }
}
