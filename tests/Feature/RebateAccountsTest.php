<?php

namespace Tests\Feature;

use App\Actions\Rebates\IbAccounts;
use App\Actions\Rebates\ManageBrokers;
use App\Actions\Rebates\PayoutRules;
use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\RebateLedger;
use App\Livewire\Rebates\Accounts;
use App\Livewire\Rebates\AdminAccounts;
use App\Livewire\Rebates\AdminWithdrawals;
use App\Livewire\Rebates\Brokers;
use App\Livewire\Rebates\Ledger;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\DomainRecords;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class RebateAccountsTest extends TestCase
{
    use RebateWorkflow,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareRebates();
    }

    private function invalid(\Closure $work, string $field): void
    {
        try {
            $work();
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_broker_logo_url_rate_history_sanitization_and_disabling_preserve_historical_data(): void
    {
        $input = ['name' => 'Synthetic Logo Broker', 'ib_link' => 'https://broker.example.invalid/ref/test', 'rate_usd_per_lot' => '2.50', 'valid_from' => now()->format('Y-m-d'), 'terms' => '<p>Real terms only</p><script>BAD-FIXTURE</script>', 'is_active' => true];
        $service = app(ManageBrokers::class);
        $broker = $service->save(null, $input, UploadedFile::fake()->image('logo.png', 200, 80));
        $this->assertStringNotContainsString('<script', $broker->terms);
        $this->get(route('broker.logo', $broker->id))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('broker'))->assertOk()->assertSee('Synthetic Logo Broker')->assertSee('Estimasi rebate');
        $account = $this->account($broker);
        $credit = $this->credit($account);
        $service->save($broker->id, [...$input, 'rate_usd_per_lot' => '3.00', 'is_active' => false]);
        $this->get(route('broker.logo', $broker->id))->assertNotFound();
        $this->get(route('broker'))->assertDontSee('Synthetic Logo Broker');
        $this->assertSame('100.0000', $credit->fresh()->amount_usd);
        $this->assertSame(2, ActivityLog::where('action', 'broker.saved')->count());
        $audit = ActivityLog::where('action', 'broker.saved')->latest('id')->first()->summary_json;
        $this->assertSame('2.5000', $audit['rate_before']);
        $this->assertSame('3.0000', $audit['rate_after']);
        $this->invalid(fn () => $service->save(null, [...$input, 'ib_link' => 'https://evil.example.invalid/x']), 'ib_link');
        $this->invalid(fn () => $service->save(null, [...$input, 'ib_link' => 'javascript:alert(1)']), 'ib_link');
        $this->invalid(fn () => $service->save(null, [...$input, 'rate_usd_per_lot' => '-1']), 'amount');
    }

    public function test_clients_can_link_two_brokers_without_license_but_cannot_claim_someone_elses_account(): void
    {
        $first = $this->broker();
        $second = $this->broker();
        $this->actingAs($this->buyer);
        $service = app(IbAccounts::class);
        $row = $service->attach($first->id, '12345', 'Synthetic ownership proof');
        $otherBroker = $service->attach($second->id, '12345', 'Synthetic second broker proof');
        $this->assertSame($row->id, $service->attach($first->id, '12345', 'Synthetic ownership proof')->id);
        $this->assertNotSame($row->id, $otherBroker->id);
        $this->assertDatabaseCount('licenses', 0);
        $other = User::findOrFail((new DomainRecords)->user());
        $this->actingAs($other);
        $this->invalid(fn () => $service->attach($first->id, '12345', 'Synthetic stolen ownership'), 'account');
        $this->get(route('member.rebate.akun'))->assertOk()->assertDontSee('12345');
        $this->invalid(fn () => $service->attach($first->id, 'abc', 'Synthetic proof'), 'account');
        $this->invalid(fn () => $service->attach($first->id, '98765', 'x'), 'proof');
        $this->assertDatabaseCount('trading_accounts', 2);
    }

    public function test_future_or_inactive_broker_rejects_new_claim_and_pending_account_cannot_receive_credit(): void
    {
        $future = $this->broker(['valid_from' => now('Asia/Jakarta')->addDay()->format('Y-m-d')]);
        $inactive = $this->broker(['is_active' => false]);
        $active = $this->broker();
        $this->actingAs($this->buyer);
        $service = app(IbAccounts::class);
        $this->invalid(fn () => $service->attach($future->id, '111', 'Synthetic broker proof'), 'brokerId');
        $this->invalid(fn () => $service->attach($inactive->id, '222', 'Synthetic broker proof'), 'brokerId');
        $pending = $service->attach($active->id, '333', 'Synthetic broker proof');
        $this->admin();
        $this->invalid(fn () => app(RebateLedger::class)->credit($pending->id, $this->entryData()), 'amount');
        $this->assertDatabaseCount('rebate_entries', 0);
    }

    public function test_approval_requires_checked_evidence_and_rejected_reason_is_visible_only_to_owner(): void
    {
        $broker = $this->broker();
        $this->actingAs($this->buyer);
        $service = app(IbAccounts::class);
        $row = $service->attach($broker->id, '12345', 'SYNTHETIC-PRIVATE-PROOF');
        $this->admin();
        $this->invalid(fn () => $service->decide($row->id, 'approved', 'Synthetic note', false), 'checked');
        $service->decide($row->id, 'rejected', 'Synthetic referral not eligible', false);
        $service->decide($row->id, 'rejected', 'Synthetic referral not eligible', false);
        $this->invalid(fn () => $service->decide($row->id, 'approved', 'Synthetic note', true), 'account');
        $this->actingAs($this->buyer);
        $this->get(route('member.rebate.akun'))->assertSee('Ditolak')->assertSee('Synthetic referral not eligible')->assertDontSee('SYNTHETIC-PRIVATE-PROOF');
        $this->assertStringNotContainsString('SYNTHETIC-PRIVATE-PROOF', DB::table('activity_logs')->get()->toJson());
        $this->assertSame(1, ActivityLog::where('action', 'ib.account_decided')->count());
    }

    public function test_member_and_admin_routes_authorize_each_mutation_and_do_not_expose_financial_destinations(): void
    {
        $a = $this->account();
        $this->credit($a);
        $this->actingAs($this->buyer);
        foreach (['member.rebate', 'member.rebate.akun', 'member.penarikan'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['admin.broker', 'admin.rebate', 'admin.rebate.akun', 'admin.penarikan'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        foreach ([Brokers::class, Ledger::class, AdminAccounts::class, AdminWithdrawals::class] as $component) {
            Livewire::test($component)->assertForbidden();
        }
        Livewire::test(Accounts::class)->call('openDecision', $a->id, 'approved')->assertForbidden();
        $this->admin();
        foreach (['admin.broker', 'admin.rebate', 'admin.rebate.akun', 'admin.penarikan'] as $route) {
            $this->get(route($route))->assertOk();
        }
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        Livewire::test(Ledger::class)->call('open')->assertForbidden();
        Livewire::test(Brokers::class)->call('open')->assertForbidden();
    }

    public function test_missing_or_paused_rules_do_not_invent_a_minimum_or_open_withdrawals(): void
    {
        $this->credit($this->account());
        DB::table('settings')->where('key', 'rebate.payout')->delete();
        $this->actingAs($this->buyer);
        $this->assertNull(app(PayoutRules::class)->get());
        $this->invalid(fn () => app(Payouts::class)->quote('10', $this->destination(), (string) Str::uuid()), 'amount');
        $this->get(route('member.penarikan'))->assertOk()->assertSee('Pengajuan pencairan belum dibuka')->assertDontSee('Periksa pencairan');
    }
}
