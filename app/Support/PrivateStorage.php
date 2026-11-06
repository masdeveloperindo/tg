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

        // Also reject indirect exposure through public/storage or another public symlink.
        $targets = [storage_path('app/public')];
        foreach (config('filesystems.links', []) as $link => $target) {
            if (self::within($link, $public)) {
                $targets[] = $target;
            }
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($public, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            if ($entry->isLink()) {
                $targets[] = $entry->getPathname();
            }
        }
        foreach ($targets as $target) {
            $resolved = realpath($target);
            if ($resolved && (self::within($root, $resolved) || self::within($resolved, $root))) {
                throw ValidationException::withMessages(['file' => 'Storage privat tidak boleh diakses melalui tautan publik.']);
            }
        }

        return $root;
    }

    private static function within(string $path, string $root): bool
    {
        return str_starts_with(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    public static function file(?string $path, string $pattern): string
    {
        abort_unless($path && preg_match($pattern, $path), 404);
        $root = self::root();
        $real = realpath(Storage::disk('local')->path($path));
        abort_unless($real && self::within($real, $root) && is_file($real), 404);

        return $real;
    }
}
