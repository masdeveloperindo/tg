<?php

namespace App\Http\Controllers;

use App\Actions\Licenses\LicenseFiles;
use App\Models\License;
use App\Models\ProductVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class LicenseDownloadController
{
    public static function fingerprint(License $license): string
    {
        return hash_hmac('sha256', implode('|', [$license->id, $license->user_id, $license->product_id, $license->key_hash, $license->status->value, $license->expires_at->format('c'), $license->mt5_account, $license->mt5_server, $license->account_type->value, $license->updated_at->format('Y-m-d H:i:s.u')]), config('app.key'));
    }

    public function request(License $license)
    {
        Gate::authorize('download', $license);
        $version = ProductVersion::where('product_id', $license->product_id)->where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->firstOrFail();
        $handle = app(LicenseFiles::class)->open($version);
        fclose($handle);

        return redirect(URL::temporarySignedRoute('licenses.download', now()->addMinutes(5)->min($license->expires_at), ['license' => $license->id, 'version' => $version->id, 'state' => self::fingerprint($license)]));
    }

    public function download(Request $request, int $license, int $version)
    {
        abort_unless($request->hasValidSignature(), 403);

        return DB::transaction(function () use ($request, $license, $version) {
            $license = License::whereKey($license)->sharedLock()->firstOrFail();
            Gate::authorize('download', $license);
            abort_unless(is_string($request->query('state')) && hash_equals(self::fingerprint($license), $request->query('state')), 403);
            $file = ProductVersion::where('product_id', $license->product_id)->whereKey($version)->where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->firstOrFail();
            $handle = app(LicenseFiles::class)->open($file);

            return response()->streamDownload(function () use ($handle): void {
                try {
                    fpassthru($handle);
                } finally {
                    fclose($handle);
                }
            }, 'trader-garuda-'.$file->version.'.ex5', ['Content-Type' => 'application/octet-stream', 'Content-Length' => (string) $file->bytes, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
        });
    }
}
