<?php

namespace App\Http\Controllers;

use App\Models\ProductMedia;
use App\Models\ProductPerformance;
use App\Models\ProductVersion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProductAssetController
{
    public function image(ProductMedia $media)
    {
        Gate::authorize('update', $media->product);
        abort_unless($media->media_type->value === 'image' && $media->disk_path && Storage::disk('local')->exists($media->disk_path), 404);

        return Storage::disk('local')->response($media->disk_path, null, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function version(ProductVersion $version)
    {
        Gate::authorize('update', $version->product);
        abort_unless(Storage::disk('local')->exists($version->path), 404);

        return Storage::disk('local')->download($version->path, $version->version.'.ex5', ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function report(ProductPerformance $performance)
    {
        Gate::authorize('update', $performance->product);
        abort_unless($performance->report_path && Storage::disk('local')->exists($performance->report_path), 404);

        return Storage::disk('local')->download($performance->report_path, 'laporan-'.$performance->id.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }
}
