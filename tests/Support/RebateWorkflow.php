<?php

namespace Tests\Support;

use App\Actions\Rebates\IbAccounts;
use App\Actions\Rebates\ManageBrokers;
use App\Actions\Rebates\PayoutRules;
use App\Actions\Rebates\Payouts;
use App\Actions\Rebates\RebateLedger;
use App\Models\Broker;
use App\Models\RebateEntry;
use App\Models\TradingAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait RebateWorkflow
{
    private User $buyer;

    private User $administrator;

    private function prepareRebates(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $session = app('session')->driver();
        $session->start();
        request()->setLaravelSession($session);
        app()->rebinding('request', static function ($app, Request $request): void {
            if (! $request->hasSession()) {
                $request->setLaravelSession($app['session']->driver());
            }
        });
        config(['tradergaruda.broker_referral_hosts' => ['broker.example.invalid']]);
        $this->buyer = User::findOrFail((new DomainRecords)->user());
        $this->administrator = User::findOrFail((new DomainRecords)->user(['role' => 'admin', 'totp_secret_enc' => Crypt::encryptString('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'totp_enabled_at' => now()]));
        $this->admin();
        app(PayoutRules::class)->save(['minimum_usd' => '10.0000', 'fx_rate_idr' => '16000.125000', 'enabled' => true]);
    }

    private function admin(): void
    {
        $this->actingAs($this->administrator);
        session()->put(['auth.totp_user_id' => $this->administrator->id, 'auth.totp_verified_at' => now()->timestamp, 'auth.admin_last_activity' => now()->timestamp, 'auth.password_confirmed_at' => now()->timestamp, 'auth.reauthenticated_at' => now()->timestamp]);
    }

    private function broker(array $changes = []): Broker
    {
        $this->admin();

        return app(ManageBrokers::class)->save(null, array_replace(['name' => 'Synthetic Broker '.Str::random(5), 'ib_link' => 'https://broker.example.invalid/ref/fixture', 'rate_usd_per_lot' => '2.5000', 'valid_from' => now()->subMonth()->format('Y-m-d'), 'terms' => 'Synthetic terms, no real broker.', 'is_active' => true], $changes));
    }

    private function account(?Broker $broker = null, string $number = '12345678'): TradingAccount
    {
        $broker ??= $this->broker();
        $this->actingAs($this->buyer);
        $row = app(IbAccounts::class)->attach($broker->id, $number, 'Synthetic broker ownership confirmation');
        $this->admin();

        return app(IbAccounts::class)->decide($row->id, 'approved', 'Synthetic ownership and referral checked', true);
    }

    private function credit(TradingAccount $account, string $amount = '100.0000', string $lots = '10.0000'): RebateEntry
    {
        $this->admin();

        return app(RebateLedger::class)->credit($account->id, $this->entryData($amount, $lots));
    }

    private function entryData(string $amount = '100.0000', string $lots = '10.0000', ?string $ref = null): array
    {
        return ['period' => now('Asia/Jakarta')->format('Y-m'), 'lots' => $lots, 'amount' => $amount, 'reference' => $ref ?? 'SYNTHETIC-'.Str::uuid(), 'memo' => 'Synthetic broker statement checked'];
    }

    private function destination(): array
    {
        return ['method' => 'bank', 'provider' => 'Synthetic Bank', 'holder' => 'Synthetic Owner', 'number' => 'TG-FIXTURE-00123456'];
    }

    private function quote(string $amount = '60.0000', ?string $intent = null): array
    {
        $this->actingAs($this->buyer);

        return app(Payouts::class)->quote($amount, $this->destination(), $intent ?? (string) Str::uuid());
    }

    private function paidProof(): array
    {
        return ['transferred' => true, 'reference' => 'SYNTHETIC-TRANSFER', 'paid_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i')];
    }
}
