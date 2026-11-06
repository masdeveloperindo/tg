<?php

namespace App\Actions\Operations;

use App\Models\License;
use App\Models\LicenseFlag;
use App\Models\LicenseLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DetectAnomalies
{
    public function run(): int
    {
        $rules = Configuration::anomaly();
        $count = 0;
        $cutoff = now()->subMinutes($rules['window_minutes']);
        License::whereHas('logs', fn ($q) => $q->where('created_at', '>=', $cutoff))->orderBy('id')->chunkById(100, function ($licenses) use ($rules, $cutoff, &$count) {
            foreach ($licenses as $item) {
                $count += DB::transaction(function () use ($item, $rules, $cutoff) {
                    $license = License::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    $logs = LicenseLog::where('license_id', $license->id)->where('created_at', '>=', $cutoff)->get(['result', 'ip_hash', 'product_version']);
                    $known = $license->product->versions()->whereNotNull('released_at')->where('released_at', '<=', now())->pluck('version')->all();
                    $observed = ['account_mismatch' => $logs->where('result', 'account_mismatch')->count(), 'multiple_ips' => $logs->pluck('ip_hash')->filter()->unique()->count(), 'rapid_verification' => $logs->count(), 'expired_retries' => $logs->whereIn('result', ['expired', 'revoked'])->count(), 'unknown_version' => $logs->filter(fn ($log) => $log->product_version && ! in_array($log->product_version, $known, true))->count()];
                    $threshold = ['account_mismatch' => $rules['mismatch_threshold'], 'multiple_ips' => $rules['ip_threshold'], 'rapid_verification' => $rules['burst_threshold'], 'expired_retries' => $rules['expired_threshold'], 'unknown_version' => 0];
                    $created = 0;
                    foreach ($observed as $rule => $number) {
                        if ($number <= $threshold[$rule]) {
                            continue;
                        }$existing = LicenseFlag::where('license_id', $license->id)->where('rule', $rule)->latest('last_seen_at')->lockForUpdate()->first();
                        $summary = json_encode(['observations' => $number, 'window_minutes' => $rules['window_minutes'], 'threshold' => $threshold[$rule]], JSON_THROW_ON_ERROR);
                        if ($existing && $existing->last_seen_at->gte(now()->subMinutes($rules['cooldown_minutes']))) {
                            $existing->forceFill(['last_seen_at' => now(), 'evidence_summary' => $summary])->save();

                            continue;
                        }
                        (new LicenseFlag)->forceFill(['license_id' => $license->id, 'rule' => $rule, 'severity' => $rule === 'account_mismatch' ? 'high' : 'medium', 'evidence_summary' => $summary, 'cooldown_key' => hash('sha256', $license->id.':'.$rule.':'.Str::uuid()), 'first_seen_at' => now(), 'last_seen_at' => now(), 'status' => 'new'])->save();
                        $created++;
                    }

                    return $created;
                }, 3);
            }
        });

        return $count;
    }
}
