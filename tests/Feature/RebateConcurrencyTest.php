<?php

namespace Tests\Feature;

use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\WalletTotals;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\DomainRecords;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class RebateConcurrencyTest extends TestCase
{
    use RebateWorkflow;

    private function race(array $operations): array
    {
        $barrier = storage_path('framework/testing/rebate-race-'.Str::uuid());
        $workers = [];
        try {
            foreach ($operations as $op) {
                $env = ['APP_ENV' => 'testing', 'DB_DATABASE' => 'tg_test', 'DB_URL' => '', 'APP_KEY' => config('app.key'), 'TG_REBATE_INPUT' => json_encode($op['input'] ?? [], JSON_THROW_ON_ERROR), 'TG_REBATE_QUOTE' => $op['quote'] ?? ''];
                $p = new Process([PHP_BINARY, base_path('tests/Support/rebate-worker.php'), $op['mode'], (string) $op['user'], (string) $op['id'], $barrier], base_path(), $env);
                $p->setTimeout(20);
                $p->start();
                $workers[] = $p;
            }
            $until = microtime(true) + 10;
            while (! str_contains($workers[0]->getOutput(), 'READY') || ! str_contains($workers[1]->getOutput(), 'READY')) {
                if (microtime(true) > $until) {
                    $this->fail('Workers did not reach barrier.');
                }usleep(10000);
            }
            file_put_contents($barrier, 'go');
            $results = [];
            foreach ($workers as $p) {
                $p->wait();
                $this->assertSame(0, $p->getExitCode(), 'Independent rebate worker failed.');
                $lines = explode("\n", trim($p->getOutput()));
                $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);

            return $results;
        } finally {
            foreach ($workers as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }@unlink($barrier);
        }
    }

    private function statuses(array $results, array $expected): void
    {
        $actual = array_column($results, 'status');
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    public function test_parallel_credits_quotes_pay_reject_cancel_and_correction_obey_one_wallet_even_with_old_snapshots(): void
    {
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            $this->prepareRebates();
            $account = $this->account();
            $credit = ['mode' => 'credit', 'user' => $this->administrator->id, 'id' => $account->id, 'input' => $this->entryData('100', '10', 'SYNTHETIC-RACE-CREDIT')];
            $results = $this->race([$credit, $credit]);
            $this->statuses($results, [200, 200]);
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $this->assertSame(1, ActivityLog::where('action', 'rebate.credited')->count());
            $first = $this->quote('60');
            $second = $this->quote('60');
            $op = ['mode' => 'request', 'user' => $this->buyer->id, 'id' => 0];
            $results = $this->race([[...$op, 'quote' => $first['token']], [...$op, 'quote' => $second['token']]]);
            $this->statuses($results, [200, 422]);
            $this->assertSame('40.0000', WalletTotals::read($this->buyer->id)['available']);
            $winner = Withdrawal::sole();
            $token = $results[0]['status'] === 200 ? $first['token'] : $second['token'];
            $this->statuses($this->race([[...$op, 'quote' => $token], [...$op, 'quote' => $token]]), [200, 200]);
            $this->assertDatabaseCount('withdrawals', 1);
            $results = $this->race([['mode' => 'paid', 'user' => $this->administrator->id, 'id' => $winner->id, 'input' => $this->paidProof()], ['mode' => 'rejected', 'user' => $this->administrator->id, 'id' => $winner->id, 'input' => ['reason' => 'Synthetic race rejection']]]);
            $this->statuses($results, [200, 422]);
            $this->assertContains($winner->fresh()->status->value, ['paid', 'rejected']);
            $this->assertSame('0.0000', WalletTotals::read($this->buyer->id)['reserved']);
            $row = app(Payouts::class)->request($this->quote('30')['token']);
            $this->statuses($this->race([['mode' => 'cancel', 'user' => $this->buyer->id, 'id' => $row->id], ['mode' => 'processing', 'user' => $this->administrator->id, 'id' => $row->id]]), [200, 422]);
            $this->assertContains($row->fresh()->status->value, ['cancelled', 'processing']);
            // A separate owner isolates correction versus reservation of the same funds.
            $this->buyer = User::findOrFail((new DomainRecords)->user());
            $account = $this->account();
            $original = $this->credit($account);
            $quote = $this->quote('60');
            $results = $this->race([['mode' => 'request', 'user' => $this->buyer->id, 'id' => 0, 'quote' => $quote['token']], ['mode' => 'correct', 'user' => $this->administrator->id, 'id' => $original->id, 'input' => $this->entryData('50', '5', 'SYNTHETIC-RACE-CORRECTION')]]);
            $this->statuses($results, [200, 422]);
            $balance = WalletTotals::read($this->buyer->id);
            $this->assertGreaterThanOrEqual(0, bccomp($balance['available'], '0', 4));
            $this->assertSame('100.0000', $original->fresh()->amount_usd);
            $this->artisan('tg:reconcile-rebates')->expectsOutputToContain('0 mismatches')->assertSuccessful();
        } finally {
            RefreshDatabaseState::$migrated = false;
        }
    }
}
