<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class DomainMigrationTest extends TestCase
{
    public function test_wallet_anchor_excludes_a_second_mysql_connection_until_the_lock_is_released(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $records = new DomainRecords;
        $user = $records->user();
        $wallet = $records->create('wallets', ['user_id' => $user]);
        $first = DB::connection();
        config(['database.connections.lock_probe' => config('database.connections.mysql')]);
        $second = DB::connection('lock_probe');

        try {
            $second->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $this->assertNotSame(
                $first->selectOne('SELECT CONNECTION_ID() AS id')->id,
                $second->selectOne('SELECT CONNECTION_ID() AS id')->id,
            );
            $first->beginTransaction();
            $first->table('wallets')->where('id', $wallet)->lockForUpdate()->first();

            $second->beginTransaction();
            try {
                $second->table('wallets')->where('id', $wallet)->lockForUpdate()->first();
                $this->fail('The second independent connection acquired an already locked wallet.');
            } catch (QueryException $exception) {
                $this->assertSame(1205, $exception->errorInfo[1]);
            } finally {
                $second->rollBack();
            }

            $first->rollBack();
            $second->beginTransaction();
            $this->assertSame($wallet, $second->table('wallets')->where('id', $wallet)->lockForUpdate()->first()->id);
            $second->rollBack();
        } finally {
            while ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            while ($second->transactionLevel() > 0) {
                $second->rollBack();
            }
            DB::purge('lock_probe');
            DB::table('wallets')->where('id', $wallet)->delete();
            DB::table('users')->where('id', $user)->delete();
            RefreshDatabaseState::$migrated = false;
        }
    }

    public function test_empty_database_migrates_rolls_back_and_migrates_again_with_circular_foreign_keys(): void
    {
        // The base TestCase verifies tg_test before any destructive command.
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
            $this->assertCount(34, $this->tables());
            $this->assertSame(1, $this->renewalForeignKeyCount());

            // The last migration must detach the cycle without dropping history tables.
            $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
            $this->assertTrue(Schema::hasTable('orders'));
            $this->assertTrue(Schema::hasTable('licenses'));
            $this->assertSame(0, $this->renewalForeignKeyCount());

            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->assertSame(1, $this->renewalForeignKeyCount());

            // Reset spans both batches: deferred FK first, then tables in reverse order.
            $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();
            $this->assertSame(['migrations'], $this->tables());

            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $this->assertCount(34, $this->tables());
            $this->assertSame(1, $this->renewalForeignKeyCount());
        } finally {
            RefreshDatabaseState::$migrated = false;
        }
    }

    private function tables(): array
    {
        $tables = Schema::getTableListing(DB::connection()->getDatabaseName(), false);
        sort($tables);

        return $tables;
    }

    private function renewalForeignKeyCount(): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?',
            ['orders', 'renewal_license_id', 'licenses'],
        )->total;
    }
}
