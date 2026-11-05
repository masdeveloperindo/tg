<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Synthetic, test-database-only records; never used by production seeders. */
final class DomainRecords
{
    public const TABLES = [
        'activity_logs', 'admin_recovery_codes', 'announcements', 'api_nonces',
        'article_images', 'articles', 'brokers', 'faqs', 'guide_steps', 'guides',
        'license_account_change_requests', 'license_activations', 'license_flags',
        'license_logs', 'licenses', 'orders', 'outbound_clicks', 'pages',
        'product_media', 'product_performances', 'product_versions', 'products',
        'rebate_entries', 'refunds', 'settings', 'testimonials', 'trading_accounts',
        'users', 'wallets', 'withdrawals',
    ];

    public const CREATED_ONLY = [
        'activity_logs', 'admin_recovery_codes', 'api_nonces', 'article_images',
        'license_logs', 'outbound_clicks', 'rebate_entries',
    ];

    private static int $sequence = 0;

    private static ?string $passwordHash = null;

    public function create(string $table, array $overrides = []): int
    {
        return (int) DB::table($table)->insertGetId($this->attributes($table, $overrides));
    }

    public function attributes(string $table, array $overrides = []): array
    {
        $sequence = ++self::$sequence;
        $now = '2026-11-04 00:00:00.123456';
        $foreign = fn (string $column, string $related = 'users') => array_key_exists($column, $overrides)
            ? $overrides[$column]
            : $this->create($related);

        $attributes = match ($table) {
            'users' => [
                'name' => 'Schema test '.$sequence,
                'email' => 'schema-'.$sequence.'@example.invalid',
                'password' => self::$passwordHash ??= Hash::make('test-only-password'),
                'role' => 'client', 'status' => 'active',
            ],
            'admin_recovery_codes' => [
                'user_id' => $foreign('user_id'), 'code_hash' => hash('sha256', 'recovery-'.$sequence),
            ],
            'products' => [
                'name' => 'Schema product '.$sequence, 'slug' => 'schema-product-'.$sequence,
                'type' => 'ea', 'status' => 'draft', 'base_price_idr' => '1250000.00',
                'minimum_price_idr' => '100000.00',
            ],
            'product_versions' => [
                'product_id' => $foreign('product_id', 'products'), 'version' => 'test-'.$sequence,
                'path' => 'test-only/version-'.$sequence.'.ex5', 'sha256' => hash('sha256', 'file-'.$sequence),
                'bytes' => 128, 'is_active' => false,
            ],
            'product_media' => [
                'product_id' => $foreign('product_id', 'products'), 'media_type' => 'image',
                'disk_path' => 'test-only/image-'.$sequence.'.png', 'section' => 'preview',
            ],
            'product_performances' => [
                'product_id' => $foreign('product_id', 'products'), 'test_type' => 'backtest',
                'account_environment' => 'demo', 'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31',
                'symbol' => 'TEST', 'timeframe' => 'H1', 'updated_by' => $foreign('updated_by'),
            ],
            'orders' => [
                'code' => 'TEST-'.$sequence, 'user_id' => $foreign('user_id'),
                'product_id' => $foreign('product_id', 'products'), 'mt5_account' => (string) (100000 + $sequence),
                'mt5_server' => 'TEST-SERVER', 'account_type' => 'demo', 'order_type' => 'new',
                'coverage_year' => 2026, 'price_locked_idr' => '1250000.00', 'status' => 'waiting',
                'idempotency_key' => (string) Str::uuid(), 'expires_at' => '2026-11-05 00:00:00.000000',
            ],
            'licenses' => $this->licenseAttributes($overrides, $sequence),
            'license_account_change_requests' => [
                'license_id' => $foreign('license_id', 'licenses'), 'old_account' => '100001',
                'new_account' => (string) (200000 + $sequence), 'new_server' => 'TEST-SERVER',
                'status' => 'pending', 'requested_at' => $now,
            ],
            'license_activations' => [
                'license_id' => $foreign('license_id', 'licenses'), 'account_number' => (string) (100000 + $sequence),
            ],
            'license_logs' => [
                'event' => 'test_verify', 'result' => 'invalid', 'request_id' => (string) Str::uuid(),
            ],
            'license_flags' => [
                'license_id' => $foreign('license_id', 'licenses'), 'rule' => 'test_rule', 'severity' => 'low',
                'cooldown_key' => hash('sha256', 'cooldown-'.$sequence), 'first_seen_at' => $now,
                'last_seen_at' => $now, 'status' => 'new',
            ],
            'brokers' => [
                'name' => 'Schema broker '.$sequence, 'ib_link' => 'https://example.invalid/test',
                'rate_usd_per_lot' => '1.2500', 'valid_from' => '2026-01-01',
            ],
            'trading_accounts' => [
                'user_id' => $foreign('user_id'), 'broker_id' => $foreign('broker_id', 'brokers'),
                'account_number' => (string) (300000 + $sequence), 'status' => 'approved',
            ],
            'wallets' => ['user_id' => $foreign('user_id')],
            'rebate_entries' => [
                'trading_account_id' => $foreign('trading_account_id', 'trading_accounts'),
                'period' => '2026-10', 'lots' => '1.2500', 'amount_usd' => '12.3456',
                'source_reference' => 'TEST-REBATE-'.$sequence, 'entered_by' => $foreign('entered_by'),
            ],
            'withdrawals' => [
                'user_id' => $foreign('user_id'), 'amount_usd' => '10.1234', 'fx_rate_idr' => '16000.123456',
                'amount_idr' => '161975.65', 'destination_enc' => Crypt::encryptString('test-only-destination'),
                'status' => 'requested', 'idempotency_key' => (string) Str::uuid(), 'reserved_at' => $now,
            ],
            'refunds' => [
                'order_id' => $foreign('order_id', 'orders'), 'amount_idr' => '125000.00',
                'status' => 'initiated', 'method' => 'manual', 'reason' => 'test-only refund',
                'actor_id' => $foreign('actor_id'), 'idempotency_key' => (string) Str::uuid(),
            ],
            'announcements' => [
                'title' => 'Schema announcement', 'body' => 'Test-only body', 'audience' => 'all',
                'created_by' => $foreign('created_by'),
            ],
            'articles' => [
                'title' => 'Schema article', 'slug' => 'test-article-'.$sequence, 'category' => 'cara_trading',
                'body' => 'Test-only content', 'visibility' => 'public', 'status' => 'draft',
            ],
            'article_images' => [
                'article_id' => $foreign('article_id', 'articles'), 'path' => 'test-only/article.png', 'bytes' => 128,
            ],
            'faqs' => ['question' => 'Test question?', 'answer' => 'Test answer.'],
            'guides' => ['slug' => 'test-guide-'.$sequence, 'title' => 'Test guide'],
            'guide_steps' => [
                'guide_id' => $foreign('guide_id', 'guides'), 'step_no' => 1, 'body' => 'Test-only step',
            ],
            'testimonials' => [
                'display_name' => 'Test-only fixture', 'content' => 'Test-only content',
                'source' => 'automated schema test', 'consent_record' => 'test-only/consent.txt',
            ],
            'pages' => ['slug' => 'test-page-'.$sequence, 'title' => 'Test page', 'body' => 'Test-only body'],
            'settings' => [
                'key' => 'test.setting.'.$sequence, 'value_encrypted' => Crypt::encryptString('test-only value'),
            ],
            'activity_logs' => [
                'action' => 'test.created', 'subject_type' => 'test', 'request_id' => (string) Str::uuid(),
                'summary_json' => json_encode(['result' => 'test-only'], JSON_THROW_ON_ERROR),
            ],
            'outbound_clicks' => ['target_type' => 'telegram'],
            'api_nonces' => [
                'nonce_sha256' => hash('sha256', 'nonce-'.$sequence), 'expires_at' => '2026-11-04 00:10:00.123456',
            ],
            default => throw new InvalidArgumentException('Unknown test domain table: '.$table),
        };

        $attributes['created_at'] = $now;
        if (! in_array($table, self::CREATED_ONLY, true)) {
            $attributes['updated_at'] = $now;
        }

        return array_replace($attributes, $overrides);
    }

    private function licenseAttributes(array $overrides, int $sequence): array
    {
        $account = $overrides['mt5_account'] ?? (string) (100000 + $sequence);
        $orderId = $overrides['issued_order_id'] ?? $this->order(array_intersect_key($overrides, array_flip(['user_id', 'product_id'])) + ['mt5_account' => $account]);
        $order = DB::table('orders')->find($orderId);

        return [
            'user_id' => $overrides['user_id'] ?? $order?->user_id ?? $this->user(),
            'product_id' => $overrides['product_id'] ?? $order?->product_id ?? $this->product(),
            'key_hash' => hash('sha256', 'test-only-license-'.$sequence),
            'key_ciphertext' => Crypt::encryptString('test-only-license-'.$sequence),
            'mt5_account' => $account, 'mt5_server' => 'TEST-SERVER', 'account_type' => 'demo',
            'status' => 'active', 'coverage_year' => 2026, 'expires_at' => '2026-12-31 17:00:00.000000',
            'issued_order_id' => $orderId,
        ];
    }

    public function user(array $overrides = []): int
    {
        return $this->create('users', $overrides);
    }

    public function product(array $overrides = []): int
    {
        return $this->create('products', $overrides);
    }

    public function order(array $overrides = []): int
    {
        return $this->create('orders', $overrides);
    }

    public function license(array $overrides = []): int
    {
        return $this->create('licenses', $overrides);
    }

    public function broker(array $overrides = []): int
    {
        return $this->create('brokers', $overrides);
    }

    public function tradingAccount(array $overrides = []): int
    {
        return $this->create('trading_accounts', $overrides);
    }

    public function withdrawal(array $overrides = []): int
    {
        return $this->create('withdrawals', $overrides);
    }

    public function rebate(array $overrides = []): int
    {
        return $this->create('rebate_entries', $overrides);
    }

    public function log(array $overrides = []): int
    {
        return $this->create('license_logs', $overrides);
    }
}
