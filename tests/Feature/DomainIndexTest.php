<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class DomainIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_uses_the_required_indexes_on_representative_history(): void
    {
        $records = new DomainRecords;
        $product = $records->product();
        $broker = $records->broker();
        $actor = $records->user(['role' => 'admin']);

        $user = $records->attributes('users');
        $users = [];
        for ($i = 0; $i < 100; $i++) {
            $users[] = array_replace($user, ['id' => 2000001 + $i, 'email' => "index-$i@example.invalid"]);
        }
        DB::table('users')->insert($users);

        $owner = 2000001;
        $order = $records->attributes('orders', ['user_id' => $owner, 'product_id' => $product]);
        $license = $records->attributes('licenses', ['user_id' => $owner, 'product_id' => $product]);
        $account = $records->attributes('trading_accounts', ['user_id' => $owner, 'broker_id' => $broker]);
        $withdrawal = $records->attributes('withdrawals', ['user_id' => $owner]);
        $rows = ['orders' => [], 'licenses' => [], 'trading_accounts' => [], 'withdrawals' => []];

        for ($i = 0; $i < 1000; $i++) {
            $id = 3000001 + $i;
            $userId = $owner + ($i % 100);
            $rows['orders'][] = array_replace($order, [
                'id' => $id, 'code' => "INDEX-$i", 'user_id' => $userId,
                'mt5_account' => (string) $id, 'idempotency_key' => (string) Str::uuid(),
                'status' => $i < 2 ? 'waiting' : 'paid',
            ]);
            $rows['licenses'][] = array_replace($license, [
                'id' => $id, 'user_id' => $userId, 'issued_order_id' => $id,
                'mt5_account' => (string) $id, 'key_hash' => hash('sha256', "index-license-$i"),
                'status' => $i < 100 ? 'active' : 'suspended',
            ]);
            $rows['trading_accounts'][] = array_replace($account, [
                'id' => $id, 'user_id' => $userId, 'account_number' => (string) $id,
            ]);
            $rows['withdrawals'][] = array_replace($withdrawal, [
                'id' => $id, 'user_id' => $userId, 'idempotency_key' => (string) Str::uuid(),
            ]);
        }
        foreach ($rows as $table => $batch) {
            foreach (array_chunk($batch, 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        $rebate = $records->attributes('rebate_entries', ['trading_account_id' => 3000001, 'entered_by' => $actor]);
        $log = $records->attributes('license_logs');
        $rebates = $logs = [];
        for ($i = 0; $i < 1000; $i++) {
            $rebates[] = array_replace($rebate, [
                'trading_account_id' => 3000001 + ($i % 100), 'source_reference' => "INDEX-REBATE-$i",
            ]);
            $logs[] = array_replace($log, [
                'license_id' => 3000001 + ($i % 100), 'request_id' => (string) Str::uuid(),
            ]);
        }
        DB::table('rebate_entries')->insert($rebates);
        DB::table('license_logs')->insert($logs);

        $queries = [
            ['orders_status_expiry_idx', 'SELECT * FROM orders WHERE status = ? AND expires_at > ?', ['waiting', '2026-11-04']],
            ['licenses_user_status_expiry_idx', 'SELECT * FROM licenses WHERE user_id = ? AND status = ? AND expires_at > ?', [$owner, 'active', '2026-11-04']],
            ['trading_accounts_user_status_idx', 'SELECT * FROM trading_accounts WHERE user_id = ? AND status = ?', [$owner, 'approved']],
            ['rebate_entries_account_period_idx', 'SELECT * FROM rebate_entries WHERE trading_account_id = ? AND period = ?', [3000001, '2026-10']],
            ['withdrawals_user_status_created_idx', 'SELECT * FROM withdrawals WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 20', [$owner, 'requested']],
            ['license_logs_license_created_idx', 'SELECT * FROM license_logs WHERE license_id = ? ORDER BY created_at DESC LIMIT 50', [3000001]],
        ];

        foreach ($queries as [$index, $query, $bindings]) {
            $plan = DB::selectOne('EXPLAIN '.$query, $bindings);
            // MySQL may prefer another selective index with the same leading columns.
            $this->assertContains($index, explode(',', $plan->possible_keys ?? ''), json_encode($plan, JSON_THROW_ON_ERROR));
            $this->assertNotNull($plan->key);
            $this->assertContains($plan->type, ['range', 'ref', 'const']);
            $this->assertLessThan(1000, (int) $plan->rows);
        }
    }
}
