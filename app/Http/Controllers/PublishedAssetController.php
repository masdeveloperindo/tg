<?php

namespace App\Http\Controllers;

use App\Models\GuideStep;
use App\Models\ProductMedia;
use App\Models\ProductPerformance;
use App\Support\PrivateStorage;

class PublishedAssetController
{
    public function media(ProductMedia $media)
    {
        abort_unless($media->product->status->value === 'published' && $media->media_type->value === 'image', 404);

        return $this->image($media->disk_path);
    }

    public function performance(ProductPerformance $performance)
    {
        abort_unless($performance->product->status->value === 'published', 404);

        return $this->image($performance->equity_image_path);
    }

    public function step(GuideStep $step)
    {
        abort_unless($step->guide->is_active && $step->guide->product?->status->value === 'published', 404);

        return $this->image($step->image_path);
    }

    private function image(?string $path)
    {
        $file = PrivateStorage::file($path, '/\Aproducts\/images\/[a-f0-9-]{36}\.webp\z/');

        return response()->file($file, ['Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-cache, private']);
    }
}
