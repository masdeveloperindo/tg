<?php

namespace App\Console\Commands;

use App\Actions\Rebates\Finance;
use App\Actions\Rebates\WalletTotals;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileRebates extends Command
{
    protected $signature = 'tg:reconcile-rebates';

    protected $description = 'Read-only check of rebate credits, reservations, paid payouts and frozen exchange amounts';

    public function handle(): int
    {
        $users = DB::table('wallets')->select('user_id')->union(DB::table('withdrawals')->select('user_id'))->union(DB::table('rebate_entries')->join('trading_accounts', 'trading_accounts.id', '=', 'rebate_entries.trading_account_id')->select('trading_accounts.user_id'))->pluck('user_id');
        $failed = 0;
        foreach ($users as $id) {
            $ok = DB::transaction(function () use ($id) {
                $wallet = Wallet::where('user_id', $id)->lockForUpdate()->first();
                $totals = WalletTotals::read((int) $id, true);
                if (! $wallet || bccomp($totals['available'], '0', 4) < 0 || bccomp($totals['lots'], '0', 4) < 0) {
                    return false;
                }
                foreach (DB::table('withdrawals')->where('user_id', $id)->lockForUpdate()->get() as $row) {
                    try {
                        if (bccomp(Finance::idr($row->amount_usd, $row->fx_rate_idr), $row->amount_idr, 2) !== 0) {
                            return false;
                        }
                    } catch (\Throwable) {
                        return false;
                    }
                    if ($row->status === 'paid' && (! $row->paid_at || ! $row->bank_reference)) {
                        return false;
                    }
                }

                return true;
            });
            if (! $ok) {
                $failed++;
                $this->error('Mismatch for wallet owner ID '.(int) $id.'. Manual review required.');
            }
        }
        $this->info('Checked '.$users->count().' wallet owners; '.$failed.' mismatches. No records changed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
