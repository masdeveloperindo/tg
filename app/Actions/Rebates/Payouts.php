<?php

namespace App\Actions\Rebates;

use App\Models\TradingAccount;
use App\Models\Withdrawal;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Payouts
{
    public static function label(string $status): string
    {
        return ['requested' => 'Diajukan', 'processing' => 'Diproses', 'paid' => 'Dibayar', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'][$status];
    }

    public function quote(string $amount, array $destination, string $intent): array
    {
        $owner = Finance::owner();
        Validator::make(['intent' => $intent, 'destination' => $destination], ['intent' => 'required|uuid', 'destination.method' => 'required|in:bank,e_wallet', 'destination.provider' => 'required|string|max:80', 'destination.holder' => 'required|string|max:120', 'destination.number' => ['required', 'string', 'regex:/\A[A-Za-z0-9+ -]{4,64}\z/']])->validate();
        $destination = array_intersect_key($destination, array_flip(['method', 'provider', 'holder', 'number']));
        $amount = Finance::decimal($amount, 4, 16, true);
        $rules = app(PayoutRules::class)->get();
        if (! $rules || ! $rules['enabled'] || bccomp($amount, $rules['minimum_usd'], 4) < 0 || ! TradingAccount::where('user_id', $owner->id)->where('status', 'approved')->sharedLock()->first()) {
            throw ValidationException::withMessages(['amount' => 'Pencairan belum dibuka, akun IB belum terverifikasi, atau nominal di bawah minimum.']);
        }
        if (bccomp($amount, WalletTotals::read($owner->id)['available'], 4) > 0) {
            throw ValidationException::withMessages(['amount' => 'Saldo tersedia tidak mencukupi.']);
        }
        $quote = ['user_id' => $owner->id, 'amount' => $amount, 'fx' => $rules['fx_rate_idr'], 'minimum' => $rules['minimum_usd'], 'idr' => Finance::idr($amount, $rules['fx_rate_idr']), 'destination' => $destination, 'intent' => $intent, 'expires' => now()->addMinutes(5)->timestamp];

        return ['token' => Crypt::encryptString(json_encode($quote, JSON_THROW_ON_ERROR)), ...$quote];
    }

    public function request(string $token): Withdrawal
    {
        $owner = Finance::owner();
        try {
            $quote = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Konfirmasi tidak valid. Muat ulang halaman.']);
        }
        if (! is_array($quote) || ($quote['user_id'] ?? null) !== $owner->id) {
            abort(403);
        }

        return DB::transaction(function () use ($owner, $quote) {
            WalletTotals::lock($owner->id);
            $prior = Withdrawal::where('user_id', $owner->id)->where('idempotency_key', $quote['intent'])->lockForUpdate()->first();
            if ($prior) {
                if (bccomp($prior->amount_usd, $quote['amount'], 4) !== 0 || bccomp($prior->amount_idr, $quote['idr'], 2) !== 0 || json_decode($prior->destination_enc, true) !== $quote['destination']) {
                    throw ValidationException::withMessages(['amount' => 'Konfirmasi ini sudah dipakai untuk data berbeda.']);
                }

                return $prior;
            }
            $rules = app(PayoutRules::class)->get(true);
            if (now()->timestamp >= $quote['expires'] || ! $rules || ! $rules['enabled'] || ! TradingAccount::where('user_id', $owner->id)->where('status', 'approved')->sharedLock()->first()) {
                throw ValidationException::withMessages(['amount' => 'Konfirmasi kedaluwarsa atau pencairan belum tersedia. Periksa status sebelum mencoba kembali.']);
            }
            $balance = WalletTotals::read($owner->id, true);
            if (bccomp($quote['amount'], $balance['available'], 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'Saldo tersedia tidak mencukupi.']);
            }
            $row = new Withdrawal;
            $row->forceFill(['user_id' => $owner->id, 'amount_usd' => $quote['amount'], 'fx_rate_idr' => $quote['fx'], 'amount_idr' => $quote['idr'], 'destination_enc' => json_encode($quote['destination'], JSON_THROW_ON_ERROR), 'destination_last4' => substr($quote['destination']['number'], -4), 'status' => 'requested', 'idempotency_key' => $quote['intent'], 'reserved_at' => now()])->save();
            Audit::record($owner, 'withdrawal.requested', $row, ['resource_id' => $owner->id]);

            return $row;
        }, 3);
    }

    public function cancel(int $id): Withdrawal
    {
        $owner = Finance::owner();

        return DB::transaction(function () use ($owner, $id) {
            WalletTotals::lock($owner->id);
            $row = Withdrawal::where('user_id', $owner->id)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($row->status->value === 'cancelled') {
                return $row;
            }
            if ($row->status->value !== 'requested') {
                throw ValidationException::withMessages(['withdrawal' => 'Hanya pengajuan yang belum diproses dapat dibatalkan.']);
            }
            $row->forceFill(['status' => 'cancelled'])->save();
            Audit::record($owner, 'withdrawal.cancelled', $row, []);

            return $row;
        }, 3);
    }

    public function decide(int $id, string $target, array $input = []): Withdrawal
    {
        $admin = Finance::admin();
        Validator::make(['target' => $target], ['target' => 'required|in:processing,paid,rejected'])->validate();
        $userId = Withdrawal::findOrFail($id)->user_id;

        return DB::transaction(function () use ($id, $target, $input, $admin, $userId) {
            WalletTotals::lock($userId);
            $row = Withdrawal::whereKey($id)->lockForUpdate()->firstOrFail();
            $state = $row->status->value;
            if ($state === $target || ($target === 'processing' && $state === 'paid')) {
                return $row;
            }
            if (! in_array($state, ['requested', 'processing'], true)) {
                throw ValidationException::withMessages(['withdrawal' => 'Pengajuan sudah berakhir. Muat ulang status.']);
            }
            $changes = ['status' => $target, 'processed_by' => $admin->id];
            if ($target === 'paid') {
                $data = Validator::make($input, ['transferred' => 'required|accepted', 'reference' => 'required|string|max:120', 'paid_at' => 'required|date_format:Y-m-d\TH:i'])->validate();
                $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['paid_at'], 'Asia/Jakarta')->utc();
                if ($time->gt(now()) || $time->lt($row->reserved_at->startOfMinute())) {
                    throw ValidationException::withMessages(['paid_at' => 'Waktu transfer harus setelah pengajuan dan tidak di masa depan.']);
                }
                $changes += ['bank_reference' => $data['reference'], 'paid_at' => $time];
            } elseif ($target === 'rejected') {
                $data = Validator::make($input, ['reason' => 'required|string|min:5|max:1000'])->validate();
                $changes['rejection_reason'] = $data['reason'];
            }
            $row->forceFill($changes)->save();
            Audit::record($admin, 'withdrawal.'.$target, $row, ['transfer_checked' => $target === 'paid', 'reference_provided' => $target === 'paid', 'reason_provided' => $target === 'rejected']);

            return $row;
        }, 3);
    }
}
