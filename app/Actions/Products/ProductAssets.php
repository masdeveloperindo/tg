<?php

namespace App\Actions\Products;

use App\Support\PrivateStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductAssets
{
    public function image(UploadedFile $file): string
    {
        $mime = $file->getMimeType();
        $extensions = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        if (! $file->isValid() || $file->getSize() > 2097152 || ! in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime] ?? [], true)) {
            throw ValidationException::withMessages(['image' => 'Gunakan JPG, PNG atau WebP maksimal 2 MiB.']);
        }
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 8000000 || max($size[0], $size[1]) > 6000) {
            throw ValidationException::withMessages(['image' => 'Dimensi gambar tidak valid atau terlalu besar (maksimal 8 megapiksel, sisi 6000 piksel).']);
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['image' => 'Pemrosesan gambar belum tersedia pada server.']);
        }
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages(['image' => 'Gambar tidak dapat dibaca.']);
        }
        $ratio = min(1, 1600 / max($size[0], $size[1]));
        $target = imagecreatetruecolor(max(1, (int) round($size[0] * $ratio)), max(1, (int) round($size[1] * $ratio)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $size[0], $size[1]);
        ob_start();
        try {
            $encoded = imagewebp($target, null, 82);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($target);
            imagedestroy($source);
        }
        if (! $encoded || ! is_string($bytes) || strlen($bytes) < 12 || strlen($bytes) > 4194304) {
            throw ValidationException::withMessages(['image' => 'Gambar tidak dapat disimpan.']);
        }

        return $this->put('products/images/'.Str::uuid().'.webp', $bytes);
    }

    public function ex5(UploadedFile $file): array
    {
        if (! $file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'ex5' || $file->getSize() < 16 || $file->getSize() > 20971520
            || ! in_array($file->getMimeType(), ['application/octet-stream', 'application/x-executable'], true)) {
            throw ValidationException::withMessages(['versionFile' => 'Gunakan file EX5 biner maksimal 20 MiB.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        // Strict format gate; a real licensed EA still requires the TG-003/TG-020 terminal PoC.
        if (! str_starts_with($bytes, "EX5\0") && ! str_starts_with($bytes, "\x7fEX5")) {
            throw ValidationException::withMessages(['versionFile' => 'Header EX5 tidak dikenali. Verifikasi file hasil kompilasi MT5.']);
        }

        return ['path' => $this->put('products/versions/'.Str::uuid().'.ex5', $bytes), 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes)];
    }

    private function put(string $path, string $bytes): string
    {
        PrivateStorage::root();
        if (! Storage::disk('local')->put($path, $bytes, ['visibility' => 'private'])) {
            throw new \RuntimeException('Private product asset write failed.');
        }

        return $path;
    }
}
