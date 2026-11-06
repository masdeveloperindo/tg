<?php

namespace App\Http\Controllers;

use App\Actions\Content\Visibility;
use App\Models\ArticleImage;
use App\Models\GuideStep;
use App\Support\PrivateStorage;
use Illuminate\Support\Facades\Storage;

class ContentImageController
{
    public function __invoke(string $type, int $id)
    {
        $private = false;
        if ($type === 'cover' || $type === 'article') {
            if ($type === 'article') {
                $image = ArticleImage::findOrFail($id);
                $article = Visibility::articles()->whereKey($image->article_id)->firstOrFail();
                $path = $image->path;
            } else {
                $article = Visibility::articles()->whereKey($id)->firstOrFail();
                $path = $article->cover_path;
            }
            abort_unless(Visibility::canRead($article), 404);
            $private = $article->visibility->value === 'client';
        } elseif ($type === 'step') {
            $step = GuideStep::findOrFail($id);
            Visibility::guides()->whereKey($step->guide_id)->firstOrFail();
            $path = $step->image_path;
        } elseif ($type === 'testimonial') {
            $path = Visibility::testimonials()->whereKey($id)->firstOrFail()->photo_path;
        } else {
            abort(404);
        }
        abort_unless($path && preg_match('/\Aproducts\/images\/[a-f0-9-]{36}\.webp\z/', $path), 404);
        $root = PrivateStorage::root();
        $file = realpath(Storage::disk('local')->path($path));
        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private', 'X-Robots-Tag' => $private ? 'noindex, nofollow' : 'noimageindex']);
    }
}
