<?php

namespace App\Http\Controllers;

use App\Models\Broker;
use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Storage;

class BrokerLogoController
{
    public function __invoke(Broker $broker)
    {
        abort_unless($broker->is_active && $broker->valid_from->format('Y-m-d') <= now('Asia/Jakarta')->toDateString(), 404);
        $path = $broker->logo_path;
        abort_unless(is_string($path) && preg_match('/\Aproducts\/images\/[a-f0-9-]{36}\.webp\z/', $path), 404);
        $root = PrivateStorage::root();
        $file = realpath(Storage::disk('local')->path($path));
        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }
}
