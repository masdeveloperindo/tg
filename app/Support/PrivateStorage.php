<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PrivateStorage
{
    public static function root(): string
    {
        $disk = Storage::disk('local');
        $path = $disk->path('');
        if (! is_dir($path)) {
            $disk->makeDirectory('');
        }$root = realpath($path);
        $public = realpath(public_path());
        if (! $root || ! $public || $root === $public || str_starts_with($root.'/', rtrim($public, '/').'/')) {
            throw ValidationException::withMessages(['file' => 'Storage privat tidak boleh berada di webroot.']);
        }

        return $root;
    }
}
