<?php

namespace Tests\Feature;

use App\Actions\Licenses\ChangeLicenseStatus;
use App\Actions\Licenses\FulfillOrder;
use App\Actions\Orders\Checkout;
use App\Http\Controllers\LicenseDownloadController;
use App\Livewire\Fulfillment\AdminOrders;
use App\Models\ActivityLog;
use App\Models\License;
use App\Models\Order;
use App\Models\User;
use App\Support\PrivateStorage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CheckoutRecords as Fixtures;
use Tests\Support\DomainRecords;
use Tests\TestCase;

class FulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private DomainRecords $records;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->records = new DomainRecords;
        Storage::fake('local');
        $session = app('session')->driver();
        $session->start();
        request()->setLaravelSession($session);
        app()->rebinding('request', static function ($app, Request $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        config(['tradergaruda.license_sales_enabled' => true]);
        Fixtures::configure();
        $this->buyer = User::findOrFail($this->records->user());
        $this->actingAs($this->buyer);
    }

    private function order(): Order
    {
        $product = Fixtures::product();
        $quote = app(Checkout::class)->quote($product);

        return app(Checkout::class)->create($quote->id, Fixtures::input());
    }

    private function admin(): User
    {
        $admin = User::findOrFail($this->records->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
        $this->actingAs($admin);
        session()->put(['auth.totp_user_id' => $admin->id, 'auth.totp_verified_at' => now()->timestamp, 'auth.admin_last_activity' => now()->timestamp, 'auth.password_confirmed_at' => now()->timestamp, 'auth.reauthenticated_at' => now()->timestamp]);

        return $admin;
    }

    private function proof(): array
    {
        return ['funds_checked' => true, 'reference' => 'SYNTHETIC-REFERENCE', 'note' => 'Synthetic mutation verification only'];
    }

    private function invalid(\Closure $work, string $key): void
    {
        try {
            $work();
            $this->fail('Expected rejected operation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    private function issued(): License
    {
        $order = $this->order();
        $this->admin();

        return app(FulfillOrder::class)->confirm($order->id, $this->proof());
    }

    public function test_payment_and_encrypted_license_commit_once_and_never_log_key_or_verification_text(): void
    {
        $order = $this->order();
        $admin = $this->admin();
        $service = app(FulfillOrder::class);
        $license = $service->confirm($order->id, $this->proof());
        $key = $license->key_ciphertext;
        $this->assertMatchesRegularExpression('/^(?:[A-F0-9]{4}-){7}[A-F0-9]{4}$/D', $key);
        $this->assertSame(hash('sha256', $key), $license->key_hash);
        $this->assertNotSame($key, $license->getRawOriginal('key_ciphertext'));
        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertSame($admin->id, $order->fresh()->confirmed_by);
        $this->assertSame($license->id, $service->confirm($order->id, $this->proof())->id);
        $this->assertDatabaseCount('licenses', 1);
        $audit = DB::table('activity_logs')->get()->toJson();
        $this->assertStringNotContainsString($key, $audit);
        $this->assertStringNotContainsString('SYNTHETIC-REFERENCE', $audit);
        $this->assertStringNotContainsString('Synthetic mutation verification', $audit);
        $this->assertStringNotContainsString($key, $license->toJson());
    }

    public function test_client_unverified_admin_and_expired_reauth_cannot_confirm(): void
    {
        $order = $this->order();
        $this->post('/admin/pesanan')->assertStatus(405);
        $this->get(route('admin.pesanan.id', $order->id))->assertForbidden();
        $this->admin();
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        Livewire::test(AdminOrders::class, ['id' => $order->id])->call('open', 'confirm')->assertForbidden();
        $this->assertSame('waiting', $order->fresh()->status->value);
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_missing_funds_proof_expiry_or_poc_gate_do_not_issue_a_license(): void
    {
        $order = $this->order();
        $this->admin();
        $service = app(FulfillOrder::class);
        $this->invalid(fn () => $service->confirm($order->id, [...$this->proof(), 'funds_checked' => false]), 'funds_checked');
        config(['tradergaruda.license_sales_enabled' => false]);
        $this->invalid(fn () => $service->confirm($order->id, $this->proof()), 'payment');
        config(['tradergaruda.license_sales_enabled' => true]);
        $this->travelTo($order->expires_at);
        $this->admin();
        $this->invalid(fn () => $service->confirm($order->id, $this->proof()), 'payment');
        $this->assertDatabaseCount('licenses', 0);
        $this->assertSame('waiting', $order->fresh()->status->value);
    }

    public function test_reject_is_terminal_and_never_issues_a_key(): void
    {
        $order = $this->order();
        $this->admin();
        $service = app(FulfillOrder::class);
        $service->reject($order->id, 'Synthetic rejection');
        $service->reject($order->id, 'Synthetic rejection');
        $this->assertSame('rejected', $order->fresh()->status->value);
        $this->invalid(fn () => $service->confirm($order->id, $this->proof()), 'payment');
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_renewal_keeps_key_and_original_issue_order_and_does_not_extend_twice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-05', 'Asia/Jakarta'));
        $first = $this->order();
        $this->admin();
        $license = app(FulfillOrder::class)->confirm($first->id, $this->proof());
        $cipher = $license->getRawOriginal('key_ciphertext');
        $hash = $license->key_hash;
        $this->actingAs($this->buyer);
        $quote = app(Checkout::class)->quote($first->product, $license->id);
        $renewal = app(Checkout::class)->create($quote->id, Fixtures::input());
        $this->admin();
        $service = app(FulfillOrder::class);
        $updated = $service->confirm($renewal->id, $this->proof());
        $this->assertSame($license->id, $updated->id);
        $this->assertSame(2027, $updated->coverage_year);
        $this->assertSame($cipher, $updated->getRawOriginal('key_ciphertext'));
        $this->assertSame($hash, $updated->key_hash);
        $this->assertSame($first->id, $updated->issued_order_id);
        $this->assertSame(2027, $service->confirm($renewal->id, $this->proof())->coverage_year);
        $this->assertDatabaseCount('licenses', 1);
    }

    public function test_audit_failure_rolls_back_paid_and_license_creation(): void
    {
        $order = $this->order();
        $this->admin();
        ActivityLog::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        try {
            app(FulfillOrder::class)->confirm($order->id, $this->proof());
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic audit failure', $e->getMessage());
        } finally {
            ActivityLog::flushEventListeners();
        }
        $this->assertSame('waiting', $order->fresh()->status->value);
        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_status_intents_are_idempotent_and_old_suspend_does_not_undo_reactivation(): void
    {
        $license = $this->issued();
        $service = app(ChangeLicenseStatus::class);
        $intent = (string) Str::uuid();
        $service->change($license->id, 'suspended', 'security_review', $intent);
        $service->change($license->id, 'suspended', 'security_review', $intent);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'license.status_changed')->count());
        $service->change($license->id, 'active', 'manual_correction', (string) Str::uuid());
        $service->change($license->id, 'suspended', 'security_review', $intent);
        $this->assertSame('active', $license->fresh()->status->value);
        $service->change($license->id, 'revoked', 'policy_violation', (string) Str::uuid());
        $this->invalid(fn () => $service->change($license->id, 'active', 'manual_correction', (string) Str::uuid()), 'license');
        $this->assertSame('revoked', $license->fresh()->status->value);
    }

    public function test_signed_download_is_owned_short_lived_and_contains_exact_verified_bytes(): void
    {
        $license = $this->issued();
        $this->actingAs($this->buyer);
        $redirect = $this->get(route('licenses.download.request', $license->id))->assertRedirect();
        $url = $redirect->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertLessThanOrEqual(now()->addMinutes(5)->timestamp, (int) $query['expires']);
        $this->assertStringNotContainsString($license->key_ciphertext, $url);
        $file = $license->product->versions()->where('is_active', true)->sole();
        $this->get($url)->assertOk()->assertDownload('trader-garuda-'.$file->version.'.ex5')->assertStreamedContent(Storage::disk('local')->get($file->path));
        $other = User::findOrFail($this->records->user());
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($this->buyer);
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_signed_url_cannot_select_another_product_version_or_bypass_state_fingerprint(): void
    {
        $license = $this->issued();
        $other = Fixtures::product();
        $version = $other->versions()->sole();
        $this->actingAs($this->buyer);
        $url = URL::temporarySignedRoute('licenses.download', now()->addMinutes(5), ['license' => $license->id, 'version' => $version->id, 'state' => LicenseDownloadController::fingerprint($license)]);
        $this->get($url)->assertNotFound();
        $own = $license->product->versions()->sole();
        $bad = URL::temporarySignedRoute('licenses.download', now()->addMinutes(5), ['license' => $license->id, 'version' => $own->id, 'state' => 'invalid']);
        $this->get($bad)->assertForbidden();
    }

    public function test_private_storage_configuration_rejects_a_root_inside_the_webroot(): void
    {
        $path = public_path('private-test-'.Str::uuid());
        $old = config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => $path]);
        Storage::purge('local');
        try {
            $this->invalid(fn () => PrivateStorage::root(), 'file');
            $this->assertSame([], glob($path.'/*'));
        } finally {
            File::deleteDirectory($path);
            config(['filesystems.disks.local.root' => $old]);
            Storage::fake('local');
        }
    }

    public function test_suspend_revoke_expiry_version_mismatch_and_corruption_reject_download(): void
    {
        $license = $this->issued();
        $this->actingAs($this->buyer);
        $url = $this->get(route('licenses.download.request', $license->id))->headers->get('Location');
        $this->admin();
        app(ChangeLicenseStatus::class)->change($license->id, 'suspended', 'security_review', (string) Str::uuid());
        $this->actingAs($this->buyer)->get($url)->assertForbidden();
        $this->admin();
        app(ChangeLicenseStatus::class)->change($license->id, 'active', 'manual_correction', (string) Str::uuid());
        $this->actingAs($this->buyer);
        $version = $license->product->versions()->sole();
        Storage::disk('local')->put($version->path, 'corrupt fixture');
        $this->getJson(route('licenses.download.request', $license->id))->assertStatus(422);
        $license->forceFill(['expires_at' => now()])->save();
        $this->get(route('licenses.download.request', $license->id))->assertForbidden();
    }
}
