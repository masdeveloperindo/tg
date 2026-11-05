<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->records = new DomainRecords;
    }

    public function test_all_thirty_domain_tables_exist_on_mysql_84_with_transactional_unicode_storage(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->assertCount(30, DomainRecords::TABLES);
        $tables = Schema::getTableListing(DB::connection()->getDatabaseName(), false);
        $expected = array_merge(DomainRecords::TABLES, ['cache', 'cache_locks', 'migrations', 'sessions', 'checkout_quotes']);
        sort($tables);
        sort($expected);
        $this->assertSame($expected, $tables);

        $metadata = DB::select('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [DB::connection()->getDatabaseName()]);
        foreach ($metadata as $table) {
            if (in_array($table->name, DomainRecords::TABLES, true)) {
                $this->assertSame('InnoDB', $table->engine, $table->name);
                $this->assertStringStartsWith('utf8mb4_', $table->collation, $table->name);
            }
        }
    }

    public function test_default_seeding_does_not_create_demo_accounts_business_data_or_secrets(): void
    {
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        foreach (DomainRecords::TABLES as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_money_lots_rates_and_performance_numbers_have_the_exact_decimal_precision(): void
    {
        $expected = [
            'products.base_price_idr' => [20, 2], 'products.minimum_price_idr' => [20, 2],
            'product_performances.initial_capital' => [20, 2], 'product_performances.net_profit_pct' => [12, 4],
            'product_performances.max_drawdown_pct' => [12, 4], 'product_performances.win_rate_pct' => [12, 4],
            'product_performances.profit_factor' => [16, 6], 'orders.price_locked_idr' => [20, 2],
            'brokers.rate_usd_per_lot' => [18, 4], 'rebate_entries.lots' => [18, 4],
            'rebate_entries.amount_usd' => [20, 4], 'withdrawals.amount_usd' => [20, 4],
            'withdrawals.fx_rate_idr' => [20, 6], 'withdrawals.amount_idr' => [20, 2],
            'refunds.amount_idr' => [20, 2],
        ];
        $actual = [];
        foreach ($this->columns() as $column) {
            $this->assertNotContains($column->data_type, ['float', 'double', 'real'], $column->table_name.'.'.$column->column_name);
            if ($column->data_type === 'decimal') {
                $actual[$column->table_name.'.'.$column->column_name] = [(int) $column->numeric_precision, (int) $column->numeric_scale];
            }
        }
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);

        $productId = $this->records->product(['base_price_idr' => '999999999999999999.99']);
        $rebateId = $this->records->rebate(['amount_usd' => '9999999999999999.1234', 'lots' => '12345678901234.5678']);
        $this->assertSame('999999999999999999.99', DB::table('products')->find($productId)->base_price_idr);
        $this->assertSame('9999999999999999.1234', DB::table('rebate_entries')->find($rebateId)->amount_usd);
        $this->assertSame('12345678901234.5678', DB::table('rebate_entries')->find($rebateId)->lots);
    }

    public function test_all_domain_timestamps_are_datetime_with_microseconds_and_created_only_tables_have_no_updated_at(): void
    {
        $columns = $this->columns();
        foreach (DomainRecords::TABLES as $table) {
            $named = array_column(array_values(array_filter($columns, fn ($column) => $column->table_name === $table)), null, 'column_name');
            $this->assertArrayHasKey('created_at', $named, $table);
            $this->assertSame(! in_array($table, DomainRecords::CREATED_ONLY, true), array_key_exists('updated_at', $named), $table);
        }
        foreach ($columns as $column) {
            if (in_array($column->data_type, ['datetime', 'timestamp'], true)) {
                $this->assertSame('datetime', $column->data_type, $column->table_name.'.'.$column->column_name);
                $this->assertSame(6, (int) $column->datetime_precision, $column->table_name.'.'.$column->column_name);
            }
        }
        $userId = $this->records->user();
        $this->assertSame('2026-11-04 00:00:00.123456', DB::table('users')->find($userId)->created_at);
        $this->assertSame('+00:00', DB::selectOne('SELECT @@session.time_zone AS timezone')->timezone);
    }

    #[DataProvider('enumColumns')]
    public function test_varchar_checks_accept_each_declared_value_but_reject_invalid_case_and_accents(string $table, string $column, array $values): void
    {
        $id = $this->records->create($table);
        foreach ($values as $value) {
            DB::table($table)->where('id', $id)->update([$column => $value]);
            $this->assertSame($value, DB::table($table)->find($id)->{$column});
        }
        $first = $values[0];
        $accented = preg_replace_callback('/[aeiou]/', fn ($match) => ['a' => 'á', 'e' => 'é', 'i' => 'í', 'o' => 'ó', 'u' => 'ú'][$match[0]], $first, 1);
        foreach (['bad', strtoupper($first), $accented] as $invalid) {
            $this->assertDatabaseRejects(fn () => DB::table($table)->where('id', $id)->update([$column => $invalid]), 3819);
        }
    }

    public static function enumColumns(): array
    {
        return [
            'user role' => ['users', 'role', ['admin', 'client']],
            'user status' => ['users', 'status', ['active', 'disabled']],
            'contact channel' => ['users', 'contact_channel', ['telegram', 'whatsapp', null]],
            'product type' => ['products', 'type', ['ea', 'indicator']],
            'product status' => ['products', 'status', ['draft', 'published', 'archived']],
            'media type' => ['product_media', 'media_type', ['image', 'video_link']],
            'media section' => ['product_media', 'section', ['preview', 'tutorial']],
            'performance type' => ['product_performances', 'test_type', ['backtest', 'forward_test']],
            'performance environment' => ['product_performances', 'account_environment', ['demo', 'real']],
            'order account type' => ['orders', 'account_type', ['real', 'demo']],
            'order type' => ['orders', 'order_type', ['new', 'renewal']],
            'order status' => ['orders', 'status', ['waiting', 'paid', 'rejected', 'expired']],
            'license account type' => ['licenses', 'account_type', ['real', 'demo']],
            'license status' => ['licenses', 'status', ['active', 'suspended', 'revoked']],
            'account change status' => ['license_account_change_requests', 'status', ['pending', 'approved', 'rejected']],
            'flag severity' => ['license_flags', 'severity', ['low', 'medium', 'high']],
            'flag status' => ['license_flags', 'status', ['new', 'reviewed', 'ignored', 'suspended']],
            'trading account status' => ['trading_accounts', 'status', ['pending', 'approved', 'rejected']],
            'withdrawal status' => ['withdrawals', 'status', ['requested', 'processing', 'paid', 'rejected', 'cancelled']],
            'refund status' => ['refunds', 'status', ['initiated', 'completed', 'rejected']],
            'announcement audience' => ['announcements', 'audience', ['all', 'clients', 'ib_clients']],
            'article category' => ['articles', 'category', ['signal', 'cara_trading']],
            'article visibility' => ['articles', 'visibility', ['public', 'client']],
            'article status' => ['articles', 'status', ['draft', 'published']],
            'outbound target type' => ['outbound_clicks', 'target_type', ['telegram', 'broker']],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_numeric_and_date_checks_reject_invalid_values(string $table, array $overrides): void
    {
        $row = $this->records->attributes($table, $overrides);
        $this->assertDatabaseRejects(fn () => DB::table($table)->insert($row), 3819);
    }

    public static function invalidNumbers(): array
    {
        return [
            'negative base price' => ['products', ['base_price_idr' => '-0.01']],
            'negative minimum price' => ['products', ['minimum_price_idr' => '-0.01']],
            'negative locked price' => ['orders', ['price_locked_idr' => '-0.01']],
            'zero USD withdrawal' => ['withdrawals', ['amount_usd' => '0.0000']],
            'negative USD withdrawal' => ['withdrawals', ['amount_usd' => '-0.0001']],
            'zero exchange rate' => ['withdrawals', ['fx_rate_idr' => '0.000000']],
            'negative exchange rate' => ['withdrawals', ['fx_rate_idr' => '-0.000001']],
            'zero refund' => ['refunds', ['amount_idr' => '0.00']],
            'negative refund' => ['refunds', ['amount_idr' => '-0.01']],
            'rating below range' => ['testimonials', ['rating' => 0]],
            'rating above range' => ['testimonials', ['rating' => 6]],
            'reversed test interval' => ['product_performances', ['starts_on' => '2026-02-01', 'ends_on' => '2026-01-31']],
        ];
    }

    #[DataProvider('uniqueKeys')]
    public function test_unique_keys_reject_duplicate_records_at_the_database(string $table, array $columns): void
    {
        $id = $this->records->create($table);
        $first = (array) DB::table($table)->find($id);
        $overrides = array_intersect_key($first, array_flip($columns));
        $row = $this->records->attributes($table, $overrides);
        $this->assertDatabaseRejects(fn () => DB::table($table)->insert($row), 1062);
    }

    public static function uniqueKeys(): array
    {
        return [
            'email' => ['users', ['email']],
            'recovery hash per user' => ['admin_recovery_codes', ['user_id', 'code_hash']],
            'product slug' => ['products', ['slug']],
            'product version' => ['product_versions', ['product_id', 'version']],
            'order code' => ['orders', ['code']],
            'order idempotency' => ['orders', ['user_id', 'idempotency_key']],
            'license lookup hash' => ['licenses', ['key_hash']],
            'permanent license triple' => ['licenses', ['user_id', 'product_id', 'mt5_account']],
            'activation' => ['license_activations', ['license_id', 'account_number']],
            'verification request' => ['license_logs', ['request_id']],
            'anomaly cooldown' => ['license_flags', ['cooldown_key']],
            'broker account ownership' => ['trading_accounts', ['broker_id', 'account_number']],
            'one wallet per user' => ['wallets', ['user_id']],
            'rebate source per account' => ['rebate_entries', ['trading_account_id', 'source_reference']],
            'withdrawal idempotency' => ['withdrawals', ['user_id', 'idempotency_key']],
            'refund idempotency' => ['refunds', ['order_id', 'idempotency_key']],
            'article slug' => ['articles', ['slug']],
            'guide slug' => ['guides', ['slug']],
            'guide sequence' => ['guide_steps', ['guide_id', 'step_no']],
            'page slug' => ['pages', ['slug']],
            'setting key' => ['settings', ['key']],
            'API nonce' => ['api_nonces', ['nonce_sha256']],
        ];
    }

    public function test_generated_active_version_uniqueness_allows_many_inactive_versions_and_safe_switching(): void
    {
        $product = $this->records->product();
        $first = $this->records->create('product_versions', ['product_id' => $product, 'is_active' => true]);
        $second = $this->records->create('product_versions', ['product_id' => $product, 'is_active' => false]);
        $third = $this->records->create('product_versions', ['product_id' => $product, 'is_active' => false]);
        $this->assertNull(DB::table('product_versions')->find($second)->active_product_id);
        $this->assertNull(DB::table('product_versions')->find($third)->active_product_id);
        $this->assertSame($product, (int) DB::table('product_versions')->find($first)->active_product_id);
        $this->assertDatabaseRejects(fn () => DB::table('product_versions')->where('id', $second)->update(['is_active' => true]), 1062);
        DB::transaction(function () use ($first, $second): void {
            DB::table('product_versions')->where('id', $first)->update(['is_active' => false]);
            DB::table('product_versions')->where('id', $second)->update(['is_active' => true]);
        });
        $this->assertSame([$second], DB::table('product_versions')->where('product_id', $product)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->records->create('product_versions', ['is_active' => true]);
    }

    public function test_license_uniqueness_survives_revocation_and_renewal_references_the_existing_row(): void
    {
        $licenseId = $this->records->license(['status' => 'revoked']);
        $license = DB::table('licenses')->find($licenseId);
        $duplicate = $this->records->attributes('licenses', ['user_id' => $license->user_id, 'product_id' => $license->product_id, 'mt5_account' => $license->mt5_account]);
        $this->assertDatabaseRejects(fn () => DB::table('licenses')->insert($duplicate), 1062);
        $renewalId = $this->records->order([
            'user_id' => $license->user_id, 'product_id' => $license->product_id,
            'mt5_account' => $license->mt5_account, 'order_type' => 'renewal', 'renewal_license_id' => $licenseId,
        ]);
        $this->assertSame($licenseId, (int) DB::table('orders')->find($renewalId)->renewal_license_id);
        $this->assertSame(1, DB::table('licenses')->where('user_id', $license->user_id)->where('product_id', $license->product_id)->where('mt5_account', $license->mt5_account)->count());
        $this->assertDatabaseRejects(fn () => DB::table('licenses')->where('id', $licenseId)->delete(), 1451);
    }

    public function test_idempotency_and_rebate_source_keys_are_scoped_to_the_declared_owner(): void
    {
        $key = (string) Str::uuid();
        $this->records->order(['idempotency_key' => $key]);
        $this->records->order(['idempotency_key' => $key]);
        $this->records->withdrawal(['idempotency_key' => $key]);
        $this->records->withdrawal(['idempotency_key' => $key]);
        $this->records->create('refunds', ['idempotency_key' => $key]);
        $this->records->create('refunds', ['idempotency_key' => $key]);
        $this->records->rebate(['source_reference' => 'same-reference']);
        $this->records->rebate(['source_reference' => 'same-reference']);
        $this->assertSame(2, DB::table('withdrawals')->where('idempotency_key', $key)->count());
        $this->assertSame(2, DB::table('refunds')->where('idempotency_key', $key)->count());
        $this->assertSame(2, DB::table('rebate_entries')->where('source_reference', 'same-reference')->count());
    }

    public function test_signed_rebate_corrections_preserve_the_original_entry(): void
    {
        $entryId = $this->records->rebate(['amount_usd' => '12.3456']);
        $entry = DB::table('rebate_entries')->find($entryId);
        $correctionId = $this->records->rebate([
            'trading_account_id' => $entry->trading_account_id, 'correction_of_id' => $entryId,
            'amount_usd' => '-12.3456', 'lots' => '-1.2500',
        ]);
        $this->assertSame('12.3456', DB::table('rebate_entries')->find($entryId)->amount_usd);
        $this->assertSame('-12.3456', DB::table('rebate_entries')->find($correctionId)->amount_usd);
        $this->assertSame('0.0000', DB::table('rebate_entries')->where('trading_account_id', $entry->trading_account_id)->sum('amount_usd'));
        $this->assertDatabaseRejects(fn () => DB::table('rebate_entries')->where('id', $entryId)->delete(), 1451);
    }

    public function test_financial_history_blocks_parent_deletion_but_optional_logs_keep_their_rows(): void
    {
        $orderId = $this->records->order();
        $order = DB::table('orders')->find($orderId);
        $this->assertDatabaseRejects(fn () => DB::table('users')->where('id', $order->user_id)->delete(), 1451);
        $this->assertDatabaseRejects(fn () => DB::table('products')->where('id', $order->product_id)->delete(), 1451);

        $actor = $this->records->user();
        $activity = $this->records->create('activity_logs', ['actor_id' => $actor]);
        DB::table('users')->where('id', $actor)->delete();
        $this->assertNull(DB::table('activity_logs')->find($activity)->actor_id);

        $license = $this->records->license();
        $log = $this->records->log(['license_id' => $license]);
        DB::table('licenses')->where('id', $license)->delete();
        $this->assertNull(DB::table('license_logs')->find($log)->license_id);
    }

    private function columns(): array
    {
        return array_values(array_filter(DB::select('SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, DATA_TYPE AS data_type, NUMERIC_PRECISION AS numeric_precision, NUMERIC_SCALE AS numeric_scale, DATETIME_PRECISION AS datetime_precision FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?', [DB::connection()->getDatabaseName()]), fn ($column) => in_array($column->table_name, DomainRecords::TABLES, true)));
    }

    private function assertDatabaseRejects(callable $operation, int $mysqlCode): void
    {
        try {
            $operation();
            $this->fail('Database accepted a row that violates the schema contract.');
        } catch (QueryException $exception) {
            $this->assertSame($mysqlCode, (int) $exception->errorInfo[1]);
        }
    }
}
