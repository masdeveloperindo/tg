<?php

namespace App\Actions\Rebates;

use App\Models\Broker;
use App\Models\TradingAccount;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class IbAccounts
{
    public function attach(int $brokerId, string $account, string $proof): TradingAccount
    {
        $owner = Finance::owner();
        Validator::make(compact('account', 'proof'), ['account' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,23}\z/'], 'proof' => 'required|string|min:10|max:2000'])->validate();
        try {
            return DB::transaction(function () use ($owner, $brokerId, $account, $proof) {
                $broker = Broker::whereKey($brokerId)->lockForUpdate()->firstOrFail();
                $prior = TradingAccount::where('broker_id', $brokerId)->where('account_number', $account)->lockForUpdate()->first();
                if ($prior) {
                    if ($prior->user_id === $owner->id && $prior->evidence_note === $proof) {
                        return $prior;
                    }
                    throw ValidationException::withMessages(['account' => 'Akun sudah terdaftar. Hubungi admin untuk pemeriksaan.']);
                }
                if (! $broker->is_active || $broker->valid_from->format('Y-m-d') > now('Asia/Jakarta')->toDateString()) {
                    throw ValidationException::withMessages(['brokerId' => 'Broker belum menerima pengajuan.']);
                }
                $row = new TradingAccount;
                $row->forceFill(['user_id' => $owner->id, 'broker_id' => $brokerId, 'account_number' => $account, 'status' => 'pending', 'evidence_note' => $proof])->save();
                Audit::record($owner, 'ib.account_attached', $row, ['proof_provided' => true]);

                return $row;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['account' => 'Akun sudah terdaftar. Periksa status pengajuan.']);
        }
    }

    public function decide(int $id, string $decision, string $note, bool $checked): TradingAccount
    {
        $admin = Finance::admin();
        Validator::make(compact('decision', 'note'), ['decision' => 'required|in:approved,rejected', 'note' => 'required|string|min:5|max:1000'])->validate();

        return DB::transaction(function () use ($id, $decision, $note, $checked, $admin) {
            $account = TradingAccount::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($account->status->value !== 'pending') {
                if ($account->status->value === $decision) {
                    return $account;
                }
                throw ValidationException::withMessages(['account' => 'Pengajuan sudah diputuskan.']);
            }
            if ($decision === 'approved' && ! $checked) {
                throw ValidationException::withMessages(['checked' => 'Periksa bukti kepemilikan dan kelayakan referral terlebih dahulu.']);
            }
            $account->forceFill(['status' => $decision, 'verification_note' => $note, 'verified_by' => $admin->id, 'verified_at' => now()])->save();
            Audit::record($admin, 'ib.account_decided', $account, ['approved' => $decision === 'approved', 'proof_checked' => $checked, 'reason_provided' => true]);

            return $account;
        }, 3);
    }
}
