<?php

namespace Tests\Feature;

use App\Actions\Licenses\ChangeAccount;
use App\Actions\Orders\CalendarPricing;
use App\Actions\Refunds\ManageRefund;
use App\Livewire\Fulfillment\AccountChanges;
use App\Livewire\Fulfillment\RefundPanel;
use App\Livewire\Licenses\MyProducts;
use App\Models\ActivityLog;
use App\Models\LicenseLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\Support\LicenseWorkflow;
use Tests\TestCase;

class LicenseOperationsTest extends TestCase
{
    use LicenseWorkflow, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareWorkflow();
    }

    private function invalid(\Closure $work, string $field): void
    {
        try {
            $work();
            $this->fail('Operation must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_products_and_plaintext_key_only_belong_to_owner_and_logs_are_limited_to_fifty(): void
    {
        $license = $this->issue();
        $other = User::findOrFail((new DomainRecords)->user());
        $foreignId = (new DomainRecords)->create('licenses', ['user_id' => $other->id]);
        for ($i = 0; $i < 55; $i++) {
            (new LicenseLog)->forceFill(['license_id' => $license->id, 'event' => 'verify', 'result' => 'valid', 'product_version' => '1.0.0', 'request_id' => (string) Str::uuid(), 'ip_hash' => hash('sha256', 'synthetic-ip')])->save();
        }
        (new LicenseLog)->forceFill(['license_id' => $foreignId, 'event' => 'verify', 'result' => 'foreign-test-only', 'request_id' => (string) Str::uuid()])->save();
        $this->actingAs($this->buyer);
        $this->get(route('member.produk'))->assertOk()->assertDontSee($license->key_ciphertext);
        $response = $this->get(route('member.produk.licenseId', $license->id))->assertOk()->assertSee($license->key_ciphertext)->assertSee('Salin key')->assertDontSee('foreign-test-only')->assertDontSee(hash('sha256', 'synthetic-ip'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        Livewire::test(MyProducts::class, ['licenseId' => $license->id])->assertViewHas('logs', fn ($logs) => $logs->count() === 50);
        $this->get(route('member.produk.licenseId', $foreignId))->assertNotFound();
        $this->actingAs($other)->get(route('member.produk.licenseId', $license->id))->assertNotFound()->assertDontSee($license->key_ciphertext);
        $this->admin();
        $this->get(route('member.produk.licenseId', $license->id))->assertForbidden();
    }

    public function test_expired_license_has_zero_days_and_no_download_but_keeps_owner_history(): void
    {
        $license = $this->issue();
        $license->forceFill(['expires_at' => now()->subDay()])->save();
        $this->actingAs($this->buyer);
        $this->get(route('member.produk.licenseId', $license->id))->assertOk()->assertSee('expired')->assertSee('Sisa hari: 0')->assertDontSee('Unduh EX5 privat')->assertSee('Perpanjang lisensi');
    }

    public function test_request_is_pending_replay_safe_and_approval_keeps_key_without_rewriting_paid_order(): void
    {
        $license = $this->issue();
        $cipher = $license->getRawOriginal('key_ciphertext');
        $old = $license->mt5_account;
        $this->actingAs($this->buyer);
        $service = app(ChangeAccount::class);
        $intent = (string) Str::uuid();
        $request = $service->request($license->id, '87654321', 'Synthetic-New-Server', $intent);
        $this->assertSame('pending', $request->status->value);
        $this->assertSame($old, $license->fresh()->mt5_account);
        $this->assertSame($request->id, $service->request($license->id, '87654321', 'Synthetic-New-Server', $intent)->id);
        $this->invalid(fn () => $service->request($license->id, '87654322', 'Synthetic-New-Server', $intent), 'account');
        $this->invalid(fn () => $service->request($license->id, '87654322', 'Synthetic-New-Server', (string) Str::uuid()), 'account');
        $this->admin();
        $service->decide($license->id, $request->id, 'approved', 'Synthetic ownership verified');
        $service->decide($license->id, $request->id, 'approved', 'Retry safe');
        $updated = $license->fresh();
        $this->assertSame('87654321', $updated->mt5_account);
        $this->assertSame('Synthetic-New-Server', $updated->mt5_server);
        $this->assertSame($cipher, $updated->getRawOriginal('key_ciphertext'));
        $this->assertSame($old, $updated->issuedOrder->mt5_account);
        $this->assertSame(1, ActivityLog::where('action', 'license.account_change_decided')->count());
    }

    public function test_old_approval_retry_does_not_restore_account_after_a_later_change(): void
    {
        $license = $this->issue();
        $service = app(ChangeAccount::class);
        $this->actingAs($this->buyer);
        $first = $service->request($license->id, '87654321', 'New-Server', (string) Str::uuid());
        $this->admin();
        $service->decide($license->id, $first->id, 'approved', 'First change checked');
        $license->forceFill(['coverage_year' => 2027, 'expires_at' => CalendarPricing::end(2027)])->save();
        $this->travelTo(CarbonImmutable::parse('2027-01-05', 'Asia/Jakarta'));
        $this->actingAs($this->buyer);
        $second = $service->request($license->id, '87654322', 'New-Server', (string) Str::uuid());
        $this->admin();
        $service->decide($license->id, $second->id, 'approved', 'Second change checked');
        $service->decide($license->id, $first->id, 'approved', 'Old retry');
        $this->assertSame('87654322', $license->fresh()->mt5_account);
    }

    public function test_change_conflict_stale_identity_and_revoked_license_reject_approval(): void
    {
        $license = $this->issue();
        $service = app(ChangeAccount::class);
        $this->actingAs($this->buyer);
        $request = $service->request($license->id, '87654321', 'New-Server', (string) Str::uuid());
        $this->admin();
        (new DomainRecords)->create('licenses', ['user_id' => $this->buyer->id, 'product_id' => $license->product_id, 'mt5_account' => '87654321']);
        $this->invalid(fn () => $service->decide($license->id, $request->id, 'approved', 'Synthetic check'), 'account');
        $license->forceFill(['status' => 'revoked'])->save();
        $this->invalid(fn () => $service->decide($license->id, $request->id, 'approved', 'Synthetic check'), 'account');
        $this->assertSame('pending', $request->fresh()->status->value);
        $service->decide($license->id, $request->id, 'rejected', 'Revoked cannot move');
        $this->assertSame('rejected', $request->fresh()->status->value);
        $this->invalid(fn () => $service->decide($license->id, $request->id, 'approved', 'Terminal decision'), 'account');
    }

    public function test_other_client_and_expired_admin_reauth_cannot_decide_or_refund(): void
    {
        $license = $this->issue();
        $this->actingAs($this->buyer);
        Livewire::test(RefundPanel::class, ['orderId' => $license->issued_order_id])->assertForbidden();
        Livewire::test(AccountChanges::class, ['licenseId' => $license->id])->assertForbidden();
        $this->admin();
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        Livewire::test(RefundPanel::class, ['orderId' => $license->issued_order_id])->call('open', 'initiate')->assertForbidden();
        Livewire::test(AccountChanges::class, ['licenseId' => $license->id])->call('open', 1, 'approved')->assertForbidden();
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_refund_reserved_totals_replay_and_manual_completion_do_not_revoke_license(): void
    {
        $license = $this->issue();
        $cipher = $license->getRawOriginal('key_ciphertext');
        $service = app(ManageRefund::class);
        $intent = (string) Str::uuid();
        $refund = $service->initiate($license->issued_order_id, '100000.00', 'Synthetic refund approved', $intent);
        $this->assertSame($refund->id, $service->initiate($license->issued_order_id, '100000', 'Synthetic refund approved', $intent)->id);
        $this->invalid(fn () => $service->initiate($license->issued_order_id, '100001', 'Synthetic refund approved', $intent), 'refund');
        $this->invalid(fn () => $service->initiate($license->issued_order_id, $license->issuedOrder->price_locked_idr, 'Over amount rejected', (string) Str::uuid()), 'refund');
        $this->invalid(fn () => $service->decide($license->issued_order_id, $refund->id, 'completed', false, 'SYNTHETIC'), 'refund');
        $service->decide($license->issued_order_id, $refund->id, 'completed', true, 'SYNTHETIC-BANK-REF');
        $service->decide($license->issued_order_id, $refund->id, 'completed', true, 'SYNTHETIC-BANK-REF');
        $this->assertSame('completed', $refund->fresh()->status->value);
        $this->assertNotNull($refund->fresh()->refunded_at);
        $this->assertSame('paid', $license->issuedOrder->fresh()->status->value);
        $this->assertSame('active', $license->fresh()->status->value);
        $this->assertSame($cipher, $license->fresh()->getRawOriginal('key_ciphertext'));
        $this->assertStringNotContainsString('SYNTHETIC-BANK-REF', DB::table('activity_logs')->get()->toJson());
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_rejected_refund_releases_reserved_amount_and_never_sets_transfer_date(): void
    {
        $license = $this->issue();
        $service = app(ManageRefund::class);
        $amount = $license->issuedOrder->price_locked_idr;
        $first = $service->initiate($license->issued_order_id, $amount, 'Synthetic reason', (string) Str::uuid());
        $service->decide($license->issued_order_id, $first->id, 'rejected', false, '');
        $this->assertNull($first->fresh()->refunded_at);
        $second = $service->initiate($license->issued_order_id, $amount, 'Second synthetic reason', (string) Str::uuid());
        $service->decide($license->issued_order_id, $second->id, 'completed', true, 'SYNTHETIC');
        $this->invalid(fn () => $service->decide($license->issued_order_id, $first->id, 'completed', true, 'SYNTHETIC'), 'refund');
        $this->assertSame('paid', $license->issuedOrder->fresh()->status->value);
    }

    public function test_audit_failure_rolls_back_refund_and_account_approval(): void
    {
        $license = $this->issue();
        $this->actingAs($this->buyer);
        $change = app(ChangeAccount::class)->request($license->id, '87654321', 'New-Server', (string) Str::uuid());
        $this->admin();
        ActivityLog::creating(fn () => throw new \RuntimeException('Synthetic failure'));
        try {
            foreach ([fn () => app(ManageRefund::class)->initiate($license->issued_order_id, '1.00', 'Synthetic reason', (string) Str::uuid()), fn () => app(ChangeAccount::class)->decide($license->id, $change->id, 'approved', 'Synthetic checked')] as $work) {
                try {
                    $work();
                    $this->fail();
                } catch (\RuntimeException $e) {
                    $this->assertSame('Synthetic failure', $e->getMessage());
                }
            }
        } finally {
            ActivityLog::flushEventListeners();
        }
        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame('pending', $change->fresh()->status->value);
        $this->assertSame($license->mt5_account, $license->fresh()->mt5_account);
    }

    public function test_one_approved_account_move_per_calendar_service_year_even_after_early_renewal(): void
    {
        $license = $this->issue();
        $service = app(ChangeAccount::class);
        $this->actingAs($this->buyer);
        $change = $service->request($license->id, '87654321', 'New-Server', (string) Str::uuid());
        $this->admin();
        $service->decide($license->id, $change->id, 'approved', 'Synthetic checked');
        $license->forceFill(['coverage_year' => 2027, 'expires_at' => CalendarPricing::end(2027)])->save();
        $this->actingAs($this->buyer);
        $this->invalid(fn () => $service->request($license->id, '87654322', 'Next-Server', (string) Str::uuid()), 'account');
        $this->assertSame(0, ChangeAccount::remaining($license));
        $this->get(route('member.produk.licenseId', $license->id))->assertOk()->assertSee('Sisa perubahan akun tahun ini: 0')->assertDontSee('Ajukan ganti akun MT5');
        $this->assertSame('87654321', $license->fresh()->mt5_account);
    }
}
