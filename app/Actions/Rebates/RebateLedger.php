<?php

namespace App\Actions\Rebates;

use App\Models\RebateEntry;
use App\Models\TradingAccount;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RebateLedger
{
    private function data(array $input): array
    {
        $data = Validator::make($input, ['period' => ['required', 'date_format:Y-m'], 'lots' => 'required|string', 'amount' => 'required|string', 'reference' => ['required', 'string', 'max:100', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._\/-]{0,99}\z/'], 'memo' => 'required|string|min:5|max:1000'])->validate();
        if ($data['period'] > now('Asia/Jakarta')->format('Y-m')) {
            throw ValidationException::withMessages(['period' => 'Periode belum terjadi.']);
        }
        $data['lots'] = Finance::decimal($data['lots'], 4, 14);
        $data['amount'] = Finance::decimal($data['amount'], 4, 16);

        return $data;
    }

    public function credit(int $accountId, array $input): RebateEntry
    {
        $admin = Finance::admin();
        $data = $this->data($input);
        $owner = TradingAccount::findOrFail($accountId)->user_id;

        return DB::transaction(function () use ($accountId, $owner, $admin, $data) {
            WalletTotals::lock($owner);
            $account = TradingAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $prior = RebateEntry::where('trading_account_id', $accountId)->where('source_reference', $data['reference'])->lockForUpdate()->first();
            if ($prior) {
                $this->same($prior, $data);

                return $prior;
            }
            if ($account->status->value !== 'approved' || bccomp($data['amount'], '0', 4) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Rebate positif hanya untuk akun IB terverifikasi.']);
            }
            $entry = $this->append($accountId, $data, $admin->id);
            Audit::record($admin, 'rebate.credited', $entry, ['resource_id' => $accountId]);

            return $entry;
        }, 3);
    }

    public function correct(int $id, array $input): RebateEntry
    {
        $admin = Finance::admin();
        $data = $this->data($input);
        $target = RebateEntry::with('tradingAccount')->findOrFail($id);

        return DB::transaction(function () use ($id, $target, $admin, $data) {
            WalletTotals::lock($target->tradingAccount->user_id);
            $original = RebateEntry::whereKey($id)->lockForUpdate()->firstOrFail();
            $account = TradingAccount::whereKey($original->trading_account_id)->lockForUpdate()->firstOrFail();
            $replacementRef = $data['reference'].':replacement';
            $prior = RebateEntry::where('trading_account_id', $account->id)->where('source_reference', $replacementRef)->lockForUpdate()->first();
            if ($prior) {
                if ($prior->correction_of_id !== $id) {
                    throw ValidationException::withMessages(['reference' => 'Referensi sudah dipakai.']);
                }
                $this->same($prior, $data);

                return $prior;
            }
            if ($account->status->value !== 'approved' || bccomp($original->amount_usd, '0', 4) < 0 || str_ends_with($original->source_reference, ':reversal') || $original->corrections()->lockForUpdate()->first() !== null || $data['period'] !== $original->period) {
                throw ValidationException::withMessages(['amount' => 'Entri sudah dikoreksi atau bukan sumber yang dapat diperbaiki. Gunakan entri pengganti terbaru.']);
            }
            $balance = WalletTotals::read($account->user_id, true);
            if (bccomp(bcadd(bcsub($balance['available'], $original->amount_usd, 4), $data['amount'], 4), '0', 4) < 0) {
                throw ValidationException::withMessages(['amount' => 'Koreksi akan membuat saldo tersedia negatif. Periksa penarikan yang masih ditahan.']);
            }
            $reverse = ['period' => $original->period, 'lots' => bcsub('0', $original->lots, 4), 'amount' => bcsub('0', $original->amount_usd, 4), 'reference' => $data['reference'].':reversal', 'memo' => $data['memo']];
            $this->append($account->id, $reverse, $admin->id, $id);
            $data['reference'] = $replacementRef;
            $replacement = $this->append($account->id, $data, $admin->id, $id);
            Audit::record($admin, 'rebate.corrected', $replacement, ['resource_id' => $id]);

            return $replacement;
        }, 3);
    }

    private function same(RebateEntry $prior, array $data): void
    {
        if ($prior->period !== $data['period'] || bccomp($prior->lots, $data['lots'], 4) !== 0 || bccomp($prior->amount_usd, $data['amount'], 4) !== 0 || $prior->memo !== $data['memo']) {
            throw ValidationException::withMessages(['reference' => 'Referensi sudah digunakan dengan data berbeda.']);
        }
    }

    private function append(int $accountId, array $data, int $admin, ?int $original = null): RebateEntry
    {
        $row = new RebateEntry;
        $row->forceFill(['trading_account_id' => $accountId, 'period' => $data['period'], 'lots' => $data['lots'], 'amount_usd' => $data['amount'], 'source_reference' => $data['reference'], 'correction_of_id' => $original, 'entered_by' => $admin, 'memo' => $data['memo']])->save();

        return $row;
    }
}
