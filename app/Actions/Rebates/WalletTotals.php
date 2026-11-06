<?php

namespace App\Actions\Rebates;

use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class WalletTotals
{
    public static function lock(int $userId): Wallet
    {
        // Every money mutation uses this same anchor, including the first credit/request.
        DB::table('wallets')->insertOrIgnore(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    public static function read(int $userId, bool $locked = false): array
    {
        return DB::transaction(function () use ($userId, $locked) {
            $entries = DB::table('rebate_entries')->join('trading_accounts', 'trading_accounts.id', '=', 'rebate_entries.trading_account_id')->where('trading_accounts.user_id', $userId)->select('rebate_entries.amount_usd', 'rebate_entries.lots');
            $withdrawals = DB::table('withdrawals')->where('user_id', $userId)->select('amount_usd', 'status');
            // Current locking reads are essential: a snapshot taken before waiting on the
            // wallet lock must not hide a credit/reservation committed by another worker.
            if ($locked) {
                $entries->lockForUpdate();
                $withdrawals->lockForUpdate();
            }
            $credit = '0.0000';
            $lots = '0.0000';
            $reserved = '0.0000';
            $paid = '0.0000';
            $rows = $entries->get();
            foreach ($rows as $row) {
                $credit = bcadd($credit, $row->amount_usd, 4);
                $lots = bcadd($lots, $row->lots, 4);
            }
            foreach ($withdrawals->get() as $row) {
                if (in_array($row->status, ['requested', 'processing'], true)) {
                    $reserved = bcadd($reserved, $row->amount_usd, 4);
                } elseif ($row->status === 'paid') {
                    $paid = bcadd($paid, $row->amount_usd, 4);
                }
            }

            return ['credited' => $credit, 'lots' => $lots, 'reserved' => $reserved, 'paid' => $paid, 'available' => bcsub(bcsub($credit, $reserved, 4), $paid, 4), 'has_entries' => $rows->isNotEmpty()];
        });
    }
}
