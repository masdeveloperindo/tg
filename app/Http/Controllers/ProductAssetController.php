<?php

namespace App\Http\Controllers;

use App\Models\ProductMedia;
use App\Models\ProductPerformance;
use App\Models\ProductVersion;
use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Gate;

class ProductAssetController
{
    public function image(ProductMedia $media)
    {
        Gate::authorize('update', $media->product);
        abort_unless($media->media_type->value === 'image', 404);
        $file = PrivateStorage::file($media->disk_path, '/\Aproducts\/images\/[a-f0-9-]{36}\.webp\z/');

        return response()->file($file, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function version(ProductVersion $version)
    {
        Gate::authorize('update', $version->product);
        $file = PrivateStorage::file($version->path, '/\Aproducts\/versions\/[a-f0-9-]{36}\.ex5\z/');

        return response()->download($file, $version->version.'.ex5', ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function report(ProductPerformance $performance)
    {
        Gate::authorize('update', $performance->product);
        $file = PrivateStorage::file($performance->report_path, '/\Aproducts\/reports\/[a-f0-9-]{36}\.pdf\z/');

        return response()->download($file, 'laporan-'.$performance->id.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }
}
