<?php

namespace Tests\Feature;

use App\Actions\Rebates\PayoutRules;
use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\RebateLedger;
use App\Actions\Rebates\WalletTotals;
use App\Livewire\Rebates\Results;
use App\Models\ActivityLog;
use App\Models\RebateEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\DomainRecords;
use Tests\Support\RebateWorkflow;
use Tests\TestCase;

class RebateFinanceTest extends TestCase
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
            $this->fail('Expected rejected operation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function httpDenied(\Closure $work, int $status): void
    {
        try {
            $work();
            $this->fail('Expected denied operation.');
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            $this->assertSame(404, $status);
        }
    }

    public function test_credits_are_verified_idempotent_immutable_and_scoped_to_owner(): void
    {
        $a = $this->account();
        $data = $this->entryData('100.1234', '4.5678');
        $ledger = app(RebateLedger::class);
        $row = $ledger->credit($a->id, $data);
        $this->assertSame($row->id, $ledger->credit($a->id, $data)->id);
        $this->invalid(fn () => $ledger->credit($a->id, [...$data, 'amount' => '100.1235']), 'reference');
        $this->assertSame('100.1234', WalletTotals::read($this->buyer->id)['available']);
        $this->assertSame('4.5678', WalletTotals::read($this->buyer->id)['lots']);
        $other = (new DomainRecords)->user();
        $this->assertSame('0.0000', WalletTotals::read($other)['available']);
        $this->assertDatabaseCount('rebate_entries', 1);
        try {
            $row->forceFill(['amount_usd' => '999'])->save();
            $this->fail();
        } catch (\LogicException) {
            $this->assertSame('100.1234', $row->fresh()->amount_usd);
        }
        $this->actingAs($this->buyer);
        $this->httpDenied(fn () => $ledger->credit($a->id, $this->entryData()), 403);
    }

    public function test_correction_appends_reversal_and_replacement_and_replay_preserves_original(): void
    {
        $a = $this->account();
        $original = $this->credit($a);
        $raw = $original->fresh()->getRawOriginal();
        $data = $this->entryData('80.1250', '8.0000', 'CORRECTION-A');
        $ledger = app(RebateLedger::class);
        $new = $ledger->correct($original->id, $data);
        $this->assertSame($new->id, $ledger->correct($original->id, $data)->id);
        $this->assertSame($raw, $original->fresh()->getRawOriginal());
        $this->assertSame('80.1250', WalletTotals::read($this->buyer->id)['available']);
        $this->assertSame('8.0000', WalletTotals::read($this->buyer->id)['lots']);
        $this->assertDatabaseCount('rebate_entries', 3);
        $this->assertSame('-100.0000', RebateEntry::where('source_reference', 'CORRECTION-A:reversal')->firstOrFail()->amount_usd);
        $this->invalid(fn () => $ledger->correct($original->id, $this->entryData('70')), 'amount');
        $this->invalid(fn () => $ledger->correct($original->id, [...$data, 'amount' => '81']), 'reference');
    }

    public function test_negative_correction_cannot_spend_funds_reserved_or_paid_elsewhere(): void
    {
        $a = $this->account();
        $original = $this->credit($a);
        $quote = $this->quote('80');
        $w = app(Payouts::class)->request($quote['token']);
        $this->admin();
        $this->invalid(fn () => app(RebateLedger::class)->correct($original->id, $this->entryData('50')), 'amount');
        app(Payouts::class)->decide($w->id, 'paid', $this->paidProof());
        $this->invalid(fn () => app(RebateLedger::class)->correct($original->id, $this->entryData('50')), 'amount');
        $this->assertSame('20.0000', WalletTotals::read($this->buyer->id)['available']);
        $this->assertDatabaseCount('rebate_entries', 1);
    }

    public function test_quote_freezes_fx_minimum_destination_and_retries_after_config_changes_return_old_receipt(): void
    {
        $this->credit($this->account());
        $quote = $this->quote('10.1234');
        $this->assertSame('161975.67', $quote['idr']);
        $this->admin();
        app(PayoutRules::class)->save(['minimum_usd' => '50', 'fx_rate_idr' => '17000', 'enabled' => true]);
        $this->actingAs($this->buyer);
        $row = app(Payouts::class)->request($quote['token']);
        $this->assertSame('16000.125000', $row->fx_rate_idr);
        $this->assertSame($quote['idr'], $row->amount_idr);
        $this->assertNotSame(json_encode($this->destination()), $row->getRawOriginal('destination_enc'));
        $this->assertStringNotContainsString('TG-FIXTURE', $row->toJson());
        $this->travel(6)->minutes();
        $this->admin();
        app(PayoutRules::class)->save(['minimum_usd' => '50', 'fx_rate_idr' => '18000', 'enabled' => false]);
        $this->actingAs($this->buyer);
        $this->assertSame($row->id, app(Payouts::class)->request($quote['token'])->id);
        $this->assertDatabaseCount('withdrawals', 1);
    }

    public function test_validation_expired_or_foreign_quote_and_changed_intent_cannot_reserve_funds(): void
    {
        $this->credit($this->account());
        foreach (['0', '-1', '1', '100.00001', '10000000000000000', '1e2'] as $amount) {
            $this->invalid(fn () => $this->quote($amount), 'amount');
        }
        $quote = $this->quote('10');
        $other = User::findOrFail((new DomainRecords)->user());
        $this->actingAs($other);
        $this->httpDenied(fn () => app(Payouts::class)->request($quote['token']), 403);
        $this->actingAs($this->buyer);
        $this->invalid(fn () => app(Payouts::class)->request('tampered'), 'amount');
        $this->travel(5)->minutes();
        $this->invalid(fn () => app(Payouts::class)->request($quote['token']), 'amount');
        $this->assertDatabaseCount('withdrawals', 0);
        $intent = (string) Str::uuid();
        $first = $this->quote('10', $intent);
        $second = $this->quote('20', $intent);
        app(Payouts::class)->request($first['token']);
        $this->invalid(fn () => app(Payouts::class)->request($second['token']), 'amount');
    }

    public function test_two_fresh_quotes_cannot_reserve_more_than_available_and_duplicate_is_one_receipt(): void
    {
        $this->credit($this->account());
        $first = $this->quote('60');
        $second = $this->quote('60');
        $service = app(Payouts::class);
        $w = $service->request($first['token']);
        $this->assertSame($w->id, $service->request($first['token'])->id);
        $this->invalid(fn () => $service->request($second['token']), 'amount');
        $totals = WalletTotals::read($this->buyer->id);
        $this->assertSame('60.0000', $totals['reserved']);
        $this->assertSame('40.0000', $totals['available']);
        $this->assertSame(1, ActivityLog::where('action', 'withdrawal.requested')->count());
    }

    public function test_processing_paid_once_and_terminal_transitions_keep_accounting_unchanged(): void
    {
        $this->credit($this->account());
        $w = app(Payouts::class)->request($this->quote()['token']);
        $this->admin();
        $service = app(Payouts::class);
        $service->decide($w->id, 'processing');
        $service->decide($w->id, 'processing');
        $this->assertSame('60.0000', WalletTotals::read($this->buyer->id)['reserved']);
        $this->invalid(fn () => $service->decide($w->id, 'paid', [...$this->paidProof(), 'transferred' => false]), 'transferred');
        $service->decide($w->id, 'paid', $this->paidProof());
        $service->decide($w->id, 'paid', $this->paidProof());
        $service->decide($w->id, 'processing');
        $this->assertSame('paid', $w->fresh()->status->value);
        $this->assertSame(1, ActivityLog::where('action', 'withdrawal.paid')->count());
        $this->invalid(fn () => $service->decide($w->id, 'rejected', ['reason' => 'Rejected after payment']), 'withdrawal');
        $this->assertSame('0.0000', WalletTotals::read($this->buyer->id)['reserved']);
        $this->assertSame('60.0000', WalletTotals::read($this->buyer->id)['paid']);
        $this->assertSame('40.0000', WalletTotals::read($this->buyer->id)['available']);
        $this->actingAs($this->buyer);
        $this->invalid(fn () => $service->cancel($w->id), 'withdrawal');
        $this->assertStringNotContainsString('SYNTHETIC-TRANSFER', DB::table('activity_logs')->get()->toJson());
    }

    public function test_reject_and_client_cancel_release_reservation_exactly_once(): void
    {
        $this->credit($this->account());
        $service = app(Payouts::class);
        $w = $service->request($this->quote('60')['token']);
        $service->cancel($w->id);
        $service->cancel($w->id);
        $this->assertSame('100.0000', WalletTotals::read($this->buyer->id)['available']);
        $w = $service->request($this->quote('60')['token']);
        $this->admin();
        $service->decide($w->id, 'processing');
        $this->actingAs($this->buyer);
        $this->invalid(fn () => $service->cancel($w->id), 'withdrawal');
        $this->admin();
        $service->decide($w->id, 'rejected', ['reason' => 'Synthetic destination correction needed']);
        $service->decide($w->id, 'rejected', ['reason' => 'Synthetic destination correction needed']);
        $this->assertSame('100.0000', WalletTotals::read($this->buyer->id)['available']);
        $this->assertSame('0.0000', WalletTotals::read($this->buyer->id)['reserved']);
    }

    public function test_other_client_disabled_user_and_stale_admin_cannot_mutate_finances_or_read_destination(): void
    {
        $this->credit($this->account());
        $w = app(Payouts::class)->request($this->quote()['token']);
        $other = User::findOrFail((new DomainRecords)->user());
        $this->actingAs($other);
        $this->httpDenied(fn () => app(Payouts::class)->cancel($w->id), 404);
        $this->buyer->forceFill(['status' => 'disabled'])->save();
        $this->actingAs($this->buyer);
        $this->httpDenied(fn () => app(Payouts::class)->cancel($w->id), 403);
        $this->admin();
        session()->put('auth.reauthenticated_at', now()->subMinutes(11)->timestamp);
        $this->httpDenied(fn () => app(Payouts::class)->decide($w->id, 'paid', $this->paidProof()), 403);
        $this->get(route('admin.penarikan.id', $w->id))->assertOk()->assertDontSee($this->destination()['number']);
        $this->assertSame('requested', $w->fresh()->status->value);
    }

    public function test_two_brokers_accounts_filters_and_end_to_end_reconciliation_match_client_admin(): void
    {
        $a = $this->account($this->broker(), '11111');
        $b = $this->account($this->broker(), '22222');
        $original = $this->credit($a, '100', '10');
        $this->credit($b, '50', '5');
        app(RebateLedger::class)->correct($original->id, $this->entryData('80', '8'));
        $service = app(Payouts::class);
        $reject = $service->request($this->quote('20')['token']);
        $this->admin();
        $service->decide($reject->id, 'rejected', ['reason' => 'Synthetic reject']);
        $paid = $service->request($this->quote('40')['token']);
        $this->admin();
        $service->decide($paid->id, 'paid', $this->paidProof());
        $cancel = $service->request($this->quote('10')['token']);
        $service->cancel($cancel->id);
        $totals = WalletTotals::read($this->buyer->id);
        $this->assertSame(['credited' => '130.0000', 'lots' => '13.0000', 'reserved' => '0.0000', 'paid' => '40.0000', 'available' => '90.0000', 'has_entries' => true], $totals);
        $this->artisan('tg:reconcile-rebates')->expectsOutputToContain('0 mismatches')->assertSuccessful();
        $this->actingAs($this->buyer);
        $this->get(route('member.rebate'))->assertOk()->assertSee('USD 90.00')->assertSee('USD 40.00');
        Livewire::test(Results::class)->set('brokerId', (string) $a->broker_id)->assertViewHas('groups', fn ($g) => $g->count() === 1 && $g->first()->total_usd === '80.0000');
        Livewire::test(Results::class)->set('accountId', '999999')->assertViewHas('groups', fn ($g) => $g->count() === 0)->assertViewHas('balance', fn ($v) => $v['available'] === '90.0000');
        $this->admin();
        $this->get(route('admin.penarikan.id', $paid->id))->assertOk()->assertSee('USD 90.00');
        DB::table('withdrawals')->where('id', $paid->id)->update(['amount_idr' => '1.00']);
        $this->artisan('tg:reconcile-rebates')->expectsOutputToContain('1 mismatches')->assertFailed();
        $this->assertSame('1.00', $paid->fresh()->amount_idr);
    }

    public function test_audit_failure_rolls_back_credit_reservation_and_paid_status(): void
    {
        $a = $this->account();
        $this->credit($a);
        $quote = $this->quote('20');
        ActivityLog::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        try {
            try {
                app(Payouts::class)->request($quote['token']);
                $this->fail();
            } catch (\RuntimeException $e) {
                $this->assertSame('Synthetic audit failure', $e->getMessage());
            }
        } finally {
            ActivityLog::flushEventListeners();
        }
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertSame('100.0000', WalletTotals::read($this->buyer->id)['available']);
        $w = app(Payouts::class)->request($quote['token']);
        $this->admin();
        ActivityLog::creating(fn () => throw new \RuntimeException('Synthetic audit failure'));
        try {
            foreach ([fn () => app(Payouts::class)->decide($w->id, 'paid', $this->paidProof()), fn () => app(RebateLedger::class)->credit($a->id, $this->entryData('10'))] as $work) {
                try {
                    $work();
                    $this->fail();
                } catch (\RuntimeException $e) {
                    $this->assertSame('Synthetic audit failure', $e->getMessage());
                }
            }
        } finally {
            ActivityLog::flushEventListeners();
        }
        $this->assertSame('requested', $w->fresh()->status->value);
        $this->assertDatabaseCount('rebate_entries', 1);
        $this->assertSame('80.0000', WalletTotals::read($this->buyer->id)['available']);
    }
}
