<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->records = new DomainRecords;
    }

    #[DataProvider('modelTables')]
    public function test_every_domain_model_maps_to_its_contract_table_and_loads_microsecond_dates(string $table, string $class): void
    {
        $id = $this->records->create($table);
        $model = $class::findOrFail($id);
        $this->assertSame($table, $model->getTable());
        $this->assertSame($id, $model->getKey());
        $this->assertInstanceOf(CarbonImmutable::class, $model->created_at);
        $this->assertSame('2026-11-04 00:00:00.123456', $model->created_at->format('Y-m-d H:i:s.u'));
        if (in_array($table, DomainRecords::CREATED_ONLY, true)) {
            $this->assertNull($model->getUpdatedAtColumn());
        } else {
            $this->assertInstanceOf(CarbonImmutable::class, $model->updated_at);
        }
    }

    public static function modelTables(): array
    {
        return [
            ['users', Models\User::class], ['admin_recovery_codes', Models\AdminRecoveryCode::class],
            ['products', Models\Product::class], ['product_versions', Models\ProductVersion::class],
            ['product_media', Models\ProductMedia::class], ['product_performances', Models\ProductPerformance::class],
            ['orders', Models\Order::class], ['licenses', Models\License::class],
            ['license_account_change_requests', Models\LicenseAccountChangeRequest::class],
            ['license_activations', Models\LicenseActivation::class], ['license_logs', Models\LicenseLog::class],
            ['license_flags', Models\LicenseFlag::class], ['brokers', Models\Broker::class],
            ['trading_accounts', Models\TradingAccount::class], ['wallets', Models\Wallet::class],
            ['rebate_entries', Models\RebateEntry::class], ['withdrawals', Models\Withdrawal::class],
            ['refunds', Models\Refund::class], ['announcements', Models\Announcement::class],
            ['articles', Models\Article::class], ['article_images', Models\ArticleImage::class],
            ['faqs', Models\Faq::class], ['guides', Models\Guide::class], ['guide_steps', Models\GuideStep::class],
            ['testimonials', Models\Testimonial::class], ['pages', Models\Page::class],
            ['settings', Models\Setting::class], ['activity_logs', Models\ActivityLog::class],
            ['outbound_clicks', Models\OutboundClick::class], ['api_nonces', Models\ApiNonce::class],
        ];
    }

    #[DataProvider('encryptedFields')]
    public function test_sensitive_model_values_are_encrypted_at_rest_and_hidden_from_serialization(string $table, string $class, string $column): void
    {
        $id = $this->records->create($table);
        $model = $class::findOrFail($id);
        $plaintext = 'synthetic-sensitive-fixture-'.$table.'-'.$column;
        $model->forceFill([$column => $plaintext])->save();
        $stored = DB::table($table)->find($id)->{$column};
        $this->assertNotSame($plaintext, $stored);
        $this->assertSame($plaintext, Crypt::decryptString($stored));
        $model = $model->fresh();
        $this->assertSame($plaintext, $model->{$column});
        $this->assertArrayNotHasKey($column, $model->toArray());
        $this->assertStringNotContainsString($plaintext, $model->toJson());
    }

    public static function encryptedFields(): array
    {
        return [
            'contact' => ['users', Models\User::class, 'contact_value_enc'],
            'TOTP' => ['users', Models\User::class, 'totp_secret_enc'],
            'license key' => ['licenses', Models\License::class, 'key_ciphertext'],
            'optional IP' => ['license_logs', Models\LicenseLog::class, 'ip_enc'],
            'withdrawal destination' => ['withdrawals', Models\Withdrawal::class, 'destination_enc'],
            'setting' => ['settings', Models\Setting::class, 'value_encrypted'],
        ];
    }

    public function test_decimal_casts_keep_money_lots_and_exchange_rates_as_exact_strings(): void
    {
        $product = Models\Product::findOrFail($this->records->product(['base_price_idr' => '999999999999999999.99']));
        $rebate = Models\RebateEntry::findOrFail($this->records->rebate(['amount_usd' => '-12.3456', 'lots' => '-1.2500']));
        $withdrawal = Models\Withdrawal::findOrFail($this->records->withdrawal(['fx_rate_idr' => '16000.123456']));
        $this->assertSame('999999999999999999.99', $product->base_price_idr);
        $this->assertSame('-12.3456', $rebate->amount_usd);
        $this->assertSame('-1.2500', $rebate->lots);
        $this->assertSame('16000.123456', $withdrawal->fx_rate_idr);
        $this->assertSame('10.1234', $withdrawal->amount_usd);
        $this->assertSame('161975.65', $withdrawal->amount_idr);
        $this->assertSame('999999999999999999.99', $product->toArray()['base_price_idr']);
    }

    public function test_user_creation_normalizes_email_hashes_password_and_does_not_mass_assign_privileges(): void
    {
        $user = Models\User::create([
            'name' => 'Model test', 'email' => '  MODEL-TEST@EXAMPLE.INVALID  ', 'password' => 'synthetic-password',
            'role' => 'admin', 'status' => 'disabled', 'must_change_password' => true,
            'totp_secret_enc' => 'synthetic-forged-secret',
        ]);
        $this->assertSame('model-test@example.invalid', $user->email);
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->totp_secret_enc);
        $this->assertTrue(Hash::check('synthetic-password', DB::table('users')->find($user->id)->password));
        $this->assertSame('argon2id', password_get_info(DB::table('users')->find($user->id)->password)['algoName']);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertNull($user->getRememberTokenName());
    }

    public function test_core_relationships_follow_owner_product_order_license_and_rebate_foreign_keys(): void
    {
        $user = $this->records->user();
        $product = $this->records->product();
        $order = $this->records->order(['user_id' => $user, 'product_id' => $product]);
        $license = $this->records->license(['user_id' => $user, 'product_id' => $product, 'issued_order_id' => $order]);
        $renewal = $this->records->order(['user_id' => $user, 'product_id' => $product, 'order_type' => 'renewal', 'renewal_license_id' => $license]);
        $wallet = $this->records->create('wallets', ['user_id' => $user]);
        $account = $this->records->tradingAccount(['user_id' => $user]);
        $entry = $this->records->rebate(['trading_account_id' => $account]);
        $correction = $this->records->rebate(['trading_account_id' => $account, 'correction_of_id' => $entry, 'amount_usd' => '-1.0000']);

        $owner = Models\User::findOrFail($user);
        $this->assertSame($wallet, $owner->wallet->id);
        $this->assertSame($account, $owner->tradingAccounts->sole()->id);
        $this->assertSame($user, Models\Order::findOrFail($order)->user->id);
        $this->assertSame($product, Models\Order::findOrFail($order)->product->id);
        $this->assertSame($license, Models\Order::findOrFail($order)->issuedLicense->id);
        $this->assertSame($license, Models\Order::findOrFail($renewal)->renewalLicense->id);
        $licensed = Models\License::findOrFail($license);
        $this->assertSame($order, $licensed->issuedOrder->id);
        $this->assertSame($renewal, $licensed->renewalOrders->sole()->id);
        $this->assertSame(LicenseStatus::Active, $licensed->status);
        $this->assertSame($entry, Models\RebateEntry::findOrFail($correction)->correctionOf->id);
        $this->assertSame($correction, Models\RebateEntry::findOrFail($entry)->corrections->sole()->id);
        $this->assertSame($account, Models\RebateEntry::findOrFail($entry)->tradingAccount->id);
    }

    public function test_timezone_aware_datetime_assignments_store_utc_microseconds_without_changing_calendar_dates(): void
    {
        $wib = CarbonImmutable::create(2027, 1, 1, 0, 0, 0, 'Asia/Jakarta')->microsecond(123456);
        $license = Models\License::findOrFail($this->records->license());
        $license->forceFill(['expires_at' => $wib])->save();
        $this->assertSame('2026-12-31 17:00:00.123456', DB::table('licenses')->find($license->id)->expires_at);
        $this->assertSame('2026-12-31 17:00:00.123456', $license->fresh()->expires_at->format('Y-m-d H:i:s.u'));
        $user = Models\User::findOrFail($this->records->user(['role' => 'admin']));
        $user->forceFill(['totp_enabled_at' => $wib])->save();
        $this->assertSame('2026-12-31 17:00:00.123456', DB::table('users')->find($user->id)->totp_enabled_at);
        $this->assertSame('Asia/Jakarta', $wib->timezoneName);
        $this->assertSame('2027-01-01 00:00:00.123456', $wib->format('Y-m-d H:i:s.u'));

        $broker = Models\Broker::findOrFail($this->records->broker());
        $broker->forceFill(['valid_from' => $wib])->save();
        $this->assertSame('2027-01-01', DB::table('brokers')->find($broker->id)->valid_from);
    }

    #[DataProvider('appendOnlyModels')]
    public function test_history_models_reject_instance_and_bulk_mutation_including_update_or_insert(string $table, string $class, string $column): void
    {
        $id = $this->records->create($table);
        $original = (array) DB::table($table)->find($id);
        $model = $class::findOrFail($id);
        $operations = [
            'instance save' => fn () => $model->forceFill([$column => 'tampered'])->save(),
            'quiet save' => fn () => $model->forceFill([$column => 'tampered'])->saveQuietly(),
            'instance delete' => fn () => $model->delete(),
            'quiet delete' => fn () => $model->deleteQuietly(),
            'bulk update' => fn () => $class::query()->whereKey($id)->update([$column => 'tampered']),
            'bulk delete' => fn () => $class::query()->whereKey($id)->delete(),
            'upsert' => fn () => $class::query()->upsert([['id' => $id, $column => 'tampered']], ['id'], [$column]),
            'updateOrInsert' => fn () => $class::query()->updateOrInsert(['id' => $id], [$column => 'tampered']),
            'bulk increment' => fn () => $class::query()->whereKey($id)->increment('id'),
        ];
        foreach ($operations as $description => $operation) {
            try {
                $operation();
                $this->fail($description.' unexpectedly rewrote append-only history.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertSame($original, (array) DB::table($table)->find($id), $description);
        }
    }

    public static function appendOnlyModels(): array
    {
        return [
            ['rebate_entries', Models\RebateEntry::class, 'memo'],
            ['activity_logs', Models\ActivityLog::class, 'action'],
        ];
    }

    public function test_append_only_models_allow_new_rows_with_created_at_and_no_updated_at(): void
    {
        $rebate = (new Models\RebateEntry)->forceFill($this->records->attributes('rebate_entries'));
        $rebate->save();
        $audit = (new Models\ActivityLog)->forceFill($this->records->attributes('activity_logs', ['summary_json' => ['result' => 'test-only']]));
        $audit->save();
        $this->assertDatabaseHas('rebate_entries', ['id' => $rebate->id, 'amount_usd' => '12.3456']);
        $this->assertDatabaseHas('activity_logs', ['id' => $audit->id, 'action' => 'test.created']);
        $this->assertSame(['result' => 'test-only'], $audit->fresh()->summary_json);
    }
}
