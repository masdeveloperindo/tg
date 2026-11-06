<?php

namespace App\Actions\Licenses;

use App\Models\ActivityLog;
use App\Models\License;
use App\Models\LicenseAccountChangeRequest;
use App\Security\Access;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ChangeAccount
{
    public static function remaining(License $license): int
    {
        $used = ActivityLog::where('subject_type', License::class)->where('subject_id', $license->id)
            ->where('action', 'license.account_change_decided')->where('summary_json->approved', true)
            ->where('summary_json->period_year', now()->timezone('Asia/Jakarta')->year)->exists();

        return $used ? 0 : 1;
    }

    private function identity(License $license): string
    {
        return hash('sha256', json_encode([$license->mt5_account, $license->mt5_server, $license->account_type->value], JSON_THROW_ON_ERROR));
    }

    public function request(int $id, string $account, string $server, string $intent): LicenseAccountChangeRequest
    {
        Validator::make(compact('account', 'server', 'intent'), ['account' => ['required', 'string', 'regex:/\A[1-9][0-9]{0,23}\z/'], 'server' => ['required', 'string', 'max:100', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'], 'intent' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($id, $account, $server, $intent) {
            $license = License::whereKey($id)->lockForUpdate()->firstOrFail();
            $owner = Auth::user()?->fresh();
            abort_unless($owner && Access::isOwner($owner, $license->user_id), 403);
            $hash = hash('sha256', $owner->id.':'.$id.':'.$intent);
            $prior = ActivityLog::where('subject_type', License::class)->where('subject_id', $id)->where('action', 'license.account_change_requested')->where('summary_json->intent_sha256', $hash)->first();
            if ($prior) {
                $request = LicenseAccountChangeRequest::findOrFail($prior->summary_json['resource_id']);
                if ($request->new_account !== $account || $request->new_server !== $server) {
                    throw ValidationException::withMessages(['account' => 'Permintaan ini sudah digunakan. Muat ulang status.']);
                }

                return $request;
            }
            if (self::remaining($license) === 0 || $license->status->value !== 'active' || ! $license->expires_at->isFuture() || ($license->mt5_account === $account && $license->mt5_server === $server) || $license->accountChangeRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['account' => 'Batas satu perubahan per tahun layanan sudah digunakan, lisensi belum memenuhi syarat, atau masih ada permintaan menunggu.']);
            }
            $request = new LicenseAccountChangeRequest;
            $request->forceFill(['license_id' => $id, 'old_account' => $license->mt5_account, 'new_account' => $account, 'new_server' => $server, 'status' => 'pending', 'requested_at' => now()])->save();
            Audit::record($owner, 'license.account_change_requested', $license, ['resource_id' => $request->id, 'intent_sha256' => $hash, 'before_sha256' => $this->identity($license)]);

            return $request;
        }, 3);
    }

    public function decide(int $id, int $requestId, string $decision, string $reason): LicenseAccountChangeRequest
    {
        Validator::make(compact('decision', 'reason'), ['decision' => 'required|in:approved,rejected', 'reason' => 'required|string|min:5|max:500'])->validate();

        return DB::transaction(function () use ($id, $requestId, $decision, $reason) {
            $license = License::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $license);
            $admin = Auth::user()?->fresh();
            abort_unless($admin && Access::hasRecentReauthentication($admin), 403);
            $request = LicenseAccountChangeRequest::where('license_id', $id)->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status->value !== 'pending') {
                if ($request->status->value !== $decision) {
                    throw ValidationException::withMessages(['account' => 'Permintaan sudah diputuskan.']);
                }

                return $request;
            }
            if ($decision === 'approved') {
                $receipt = ActivityLog::where('action', 'license.account_change_requested')->where('subject_type', License::class)->where('subject_id', $id)->where('summary_json->resource_id', $requestId)->first();
                if (self::remaining($license) === 0 || $license->status->value !== 'active' || ! $license->expires_at->isFuture() || ! $receipt || ! hash_equals($receipt->summary_json['before_sha256'], $this->identity($license))) {
                    throw ValidationException::withMessages(['account' => 'Identitas/status lisensi berubah. Tolak dan ajukan ulang setelah pemeriksaan.']);
                }
                if (License::where('user_id', $license->user_id)->where('product_id', $license->product_id)->where('mt5_account', $request->new_account)->whereKeyNot($id)->exists()) {
                    throw ValidationException::withMessages(['account' => 'Akun tujuan sudah mempunyai lisensi produk ini.']);
                }
                $license->forceFill(['mt5_account' => $request->new_account, 'mt5_server' => $request->new_server])->save();
                DB::afterCommit(fn () => Cache::forget('license:state:'.$id));
            }
            $request->forceFill(['status' => $decision, 'reason' => $reason, 'decided_by' => $admin->id, 'decided_at' => now()])->save();
            Audit::record($admin, 'license.account_change_decided', $license, ['resource_id' => $request->id, 'approved' => $decision === 'approved', 'reason_provided' => true, 'period_year' => now()->timezone('Asia/Jakarta')->year]);

            return $request;
        }, 3);
    }
}
