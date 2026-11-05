<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class DomainForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_complete_foreign_key_set_matches_the_schema_contract(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('tg_test', DB::selectOne('SELECT DATABASE() AS name')->name);

        $metadata = DB::select(<<<'SQL'
            SELECT kcu.TABLE_NAME AS table_name,
                   kcu.COLUMN_NAME AS column_name,
                   kcu.REFERENCED_TABLE_SCHEMA AS referenced_schema,
                   kcu.REFERENCED_TABLE_NAME AS referenced_table,
                   kcu.REFERENCED_COLUMN_NAME AS referenced_column,
                   rc.DELETE_RULE AS delete_rule
            FROM information_schema.KEY_COLUMN_USAGE AS kcu
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                AND rc.TABLE_NAME = kcu.TABLE_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            SQL);

        $expected = [];
        foreach (self::foreignKeys() as [$table, $column, $referencedTable, $deleteRule]) {
            $expected[$table.'.'.$column] = ['tg_test', $referencedTable, 'id', $deleteRule];
        }

        $actual = [];
        foreach ($metadata as $foreignKey) {
            $key = $foreignKey->table_name.'.'.$foreignKey->column_name;
            $this->assertArrayNotHasKey($key, $actual, 'Unexpected overlapping foreign keys on '.$key);
            $actual[$key] = [
                $foreignKey->referenced_schema,
                $foreignKey->referenced_table,
                $foreignKey->referenced_column,
                $foreignKey->delete_rule,
            ];
        }

        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('foreignKeys')]
    public function test_each_foreign_key_rejects_a_missing_parent(string $table, string $column, string $referencedTable, string $deleteRule): void
    {
        $records = new DomainRecords;
        $overrides = [$column => 999999999999];

        // Keep license fixtures from propagating the invalid parent into a new order.
        if ($table === 'licenses' && in_array($column, ['user_id', 'product_id'], true)) {
            $overrides['issued_order_id'] = $records->order();
        }

        $attributes = $records->attributes($table, $overrides);

        try {
            DB::table($table)->insert($attributes);
            $this->fail($table.'.'.$column.' accepted a missing '.$referencedTable.'.id parent ('.$deleteRule.').');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
            $this->assertSame(1452, $exception->errorInfo[1], $table.'.'.$column.' must fail specifically for its missing parent.');
        }
    }

    /** Physical foreign keys declared in technical/DATABASE_SCHEMA.md; polymorphic IDs have none. */
    public static function foreignKeys(): array
    {
        return [
            'recovery code user' => ['admin_recovery_codes', 'user_id', 'users', 'RESTRICT'],
            'version product' => ['product_versions', 'product_id', 'products', 'RESTRICT'],
            'media product' => ['product_media', 'product_id', 'products', 'RESTRICT'],
            'performance product' => ['product_performances', 'product_id', 'products', 'RESTRICT'],
            'performance version' => ['product_performances', 'version_id', 'product_versions', 'RESTRICT'],
            'performance updater' => ['product_performances', 'updated_by', 'users', 'RESTRICT'],
            'order user' => ['orders', 'user_id', 'users', 'RESTRICT'],
            'order product' => ['orders', 'product_id', 'products', 'RESTRICT'],
            'order renewal license' => ['orders', 'renewal_license_id', 'licenses', 'RESTRICT'],
            'order confirmer' => ['orders', 'confirmed_by', 'users', 'RESTRICT'],
            'license user' => ['licenses', 'user_id', 'users', 'RESTRICT'],
            'license product' => ['licenses', 'product_id', 'products', 'RESTRICT'],
            'license issuing order' => ['licenses', 'issued_order_id', 'orders', 'RESTRICT'],
            'account change license' => ['license_account_change_requests', 'license_id', 'licenses', 'RESTRICT'],
            'account change decider' => ['license_account_change_requests', 'decided_by', 'users', 'RESTRICT'],
            'activation license' => ['license_activations', 'license_id', 'licenses', 'RESTRICT'],
            'log license' => ['license_logs', 'license_id', 'licenses', 'SET NULL'],
            'flag license' => ['license_flags', 'license_id', 'licenses', 'RESTRICT'],
            'flag reviewer' => ['license_flags', 'reviewed_by', 'users', 'RESTRICT'],
            'trading account user' => ['trading_accounts', 'user_id', 'users', 'RESTRICT'],
            'trading account broker' => ['trading_accounts', 'broker_id', 'brokers', 'RESTRICT'],
            'trading account verifier' => ['trading_accounts', 'verified_by', 'users', 'RESTRICT'],
            'wallet user' => ['wallets', 'user_id', 'users', 'RESTRICT'],
            'rebate trading account' => ['rebate_entries', 'trading_account_id', 'trading_accounts', 'RESTRICT'],
            'rebate correction' => ['rebate_entries', 'correction_of_id', 'rebate_entries', 'RESTRICT'],
            'rebate author' => ['rebate_entries', 'entered_by', 'users', 'RESTRICT'],
            'withdrawal user' => ['withdrawals', 'user_id', 'users', 'RESTRICT'],
            'withdrawal processor' => ['withdrawals', 'processed_by', 'users', 'RESTRICT'],
            'refund order' => ['refunds', 'order_id', 'orders', 'RESTRICT'],
            'refund actor' => ['refunds', 'actor_id', 'users', 'RESTRICT'],
            'announcement author' => ['announcements', 'created_by', 'users', 'RESTRICT'],
            'article image article' => ['article_images', 'article_id', 'articles', 'RESTRICT'],
            'FAQ product' => ['faqs', 'product_id', 'products', 'RESTRICT'],
            'guide product' => ['guides', 'product_id', 'products', 'RESTRICT'],
            'step guide' => ['guide_steps', 'guide_id', 'guides', 'RESTRICT'],
            'testimonial product' => ['testimonials', 'product_id', 'products', 'RESTRICT'],
            'setting updater' => ['settings', 'updated_by', 'users', 'RESTRICT'],
            'activity actor' => ['activity_logs', 'actor_id', 'users', 'SET NULL'],
        ];
    }
}
