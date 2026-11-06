<?php

namespace App\Actions\Operations;

use App\Actions\Licenses\ChangeLicenseStatus;
use App\Actions\Rebates\Finance;
use App\Models\License;
use App\Models\LicenseFlag;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReviewAnomaly
{
    public function decide(int $id, string $target, string $note): LicenseFlag
    {
        $admin = Finance::admin();
        Validator::make(compact('target', 'note'), ['target' => 'required|in:reviewed,ignored,suspended', 'note' => 'required|string|min:5|max:1000'])->validate();
        $licenseId = LicenseFlag::findOrFail($id)->license_id;

        return DB::transaction(function () use ($id, $licenseId, $admin, $target, $note) {
            $license = License::whereKey($licenseId)->lockForUpdate()->firstOrFail();
            $flag = LicenseFlag::whereKey($id)->lockForUpdate()->firstOrFail();
            $before = $flag->status->value;
            if ($before === $target) {
                return $flag;
            }if (! in_array($before, ['new', 'reviewed'], true)) {
                throw ValidationException::withMessages(['flag' => 'Flag sudah diputuskan.']);
            }
            if ($target === 'suspended') {
                app(ChangeLicenseStatus::class)->change($license->id, 'suspended', 'security_review', (string) Str::uuid());
            }
            $flag->forceFill(['status' => $target, 'review_note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            Audit::record($admin, 'anomaly.reviewed', $flag, ['flag_status' => $target, 'reason_provided' => true]);

            return $flag;
        }, 3);
    }
}
