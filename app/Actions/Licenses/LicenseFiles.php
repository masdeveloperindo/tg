<?php

namespace App\Actions\Licenses;

use App\Models\ProductVersion;
use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class LicenseFiles
{
    public function open(ProductVersion $version)
    {
        $root = PrivateStorage::root();
        if (! preg_match('/\Aproducts\/versions\/[a-f0-9-]{36}\.ex5\z/', $version->path) || ! Storage::disk('local')->exists($version->path)) {
            throw ValidationException::withMessages(['file' => 'File produk belum tersedia.']);
        }
        $real = realpath(Storage::disk('local')->path($version->path));
        if (! $real || ! str_starts_with($real, $root.'/')) {
            throw ValidationException::withMessages(['file' => 'Lokasi file tidak valid.']);
        }
        $handle = @fopen(Storage::disk('local')->path($version->path), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'File produk tidak dapat dibaca.']);
        }
        if (! flock($handle, LOCK_SH)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'File sedang tidak tersedia.']);
        }
        $hash = hash_init('sha256');
        hash_update_stream($hash, $handle);
        $actual = hash_final($hash);
        $size = fstat($handle)['size'];
        if ($size !== $version->bytes || ! hash_equals($version->sha256, $actual)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'Integritas file tidak sesuai. Hubungi admin.']);
        }
        rewind($handle);

        return $handle;
    }
}
