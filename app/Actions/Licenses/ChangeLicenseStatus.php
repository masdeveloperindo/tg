<?php

namespace App\Actions\Licenses;

use App\Models\ActivityLog;
use App\Models\License;
use App\Security\Access;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChangeLicenseStatus
{
    public const REASONS = ['security_review' => 'Pemeriksaan keamanan', 'client_request' => 'Permintaan pemilik', 'policy_violation' => 'Pelanggaran ketentuan', 'manual_correction' => 'Koreksi admin'];

    public function change(int $id, string $target, string $reason, string $intent): License
    {
        Validator::make(compact('target', 'reason', 'intent'), ['target' => ['required', Rule::in(['active', 'suspended', 'revoked'])], 'reason' => ['required', Rule::in(array_keys(self::REASONS))], 'intent' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($id, $target, $reason, $intent): License {
            $license = License::whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manage', $license);
            $admin = Auth::user()?->fresh();
            abort_unless($admin && Access::hasRecentReauthentication($admin), 403);
            $hash = hash('sha256', $admin->id.':'.$id.':'.$intent);
            $prior = ActivityLog::where('subject_type', License::class)->where('subject_id', $id)->where('action', 'license.status_changed')->where('summary_json->intent_sha256', $hash)->first();
            if ($prior) {
                if ($prior->summary_json['after_status'] !== $target || $prior->summary_json['reason_code'] !== $reason) {
                    throw ValidationException::withMessages(['license' => 'Intent ini sudah digunakan untuk tindakan lain.']);
                }

                return $license;
            }
            $before = $license->status->value;
            if ($before !== $target && ($before === 'revoked' || ($target === 'active' && ($before !== 'suspended' || ! $license->expires_at->isFuture())))) {
                throw ValidationException::withMessages(['license' => 'Transisi status tidak diizinkan. Lisensi revoked bersifat terminal; lisensi habis memerlukan perpanjangan.']);
            }
            $license->forceFill(['status' => $target])->save();
            Audit::record($admin, 'license.status_changed', $license, ['before_status' => $before, 'after_status' => $target, 'reason_code' => $reason, 'intent_sha256' => $hash]);
            DB::afterCommit(fn () => Cache::forget('license:state:'.$id));

            return $license;
        }, 3);
    }
}
