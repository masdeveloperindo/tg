<?php

namespace App\Http\Controllers;

use App\Actions\Rebates\Finance;
use App\Models\License;
use App\Models\ProductVersion;
use Illuminate\Support\Facades\Gate;

class VersionDownloadController
{
    public function __invoke(int $versionId)
    {
        $owner = Finance::owner();
        $version = ProductVersion::where('is_active', true)->whereNotNull('released_at')->where('released_at', '<=', now())->findOrFail($versionId);
        $license = License::where('user_id', $owner->id)->where('product_id', $version->product_id)->where('status', 'active')->where('expires_at', '>', now())->firstOrFail();
        Gate::authorize('download', $license);

        return redirect()->route('licenses.download.request', $license->id);
    }
}
