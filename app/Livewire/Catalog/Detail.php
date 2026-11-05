<?php

namespace App\Livewire\Catalog;

use App\Models\Product;
use App\Support\PublicContent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Detail extends Component
{
    #[Locked]
    public int $productId;

    public function mount(string $slug): void
    {
        $this->productId = Product::where('status', 'published')->where('slug', $slug)->firstOrFail()->id;
    }

    public function render(): View
    {
        $product = Product::whereKey($this->productId)->where('status', 'published')->with([
            'media' => fn ($query) => $query->orderBy('sort_order'),
            'versions' => fn ($query) => $query->whereNotNull('released_at')->where('released_at', '<=', now())->orderByDesc('released_at'),
            'performances' => fn ($query) => $query->with('version')->orderByDesc('ends_on'),
            'faqs' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
            'guides' => fn ($query) => $query->where('is_active', true)->with(['steps' => fn ($steps) => $steps->orderBy('step_no')]),
            'testimonials' => fn ($query) => $query->where('is_visible', true)->whereNotNull('published_at')->where('published_at', '<=', now())->whereRaw('CHAR_LENGTH(TRIM(consent_record)) > 0')->orderBy('sort_order'),
        ])->firstOrFail();
        $product->setRelation('media', $product->media->filter(fn ($media) => $media->media_type->value === 'image'
            ? PublicContent::image($media->disk_path, route('produk.media', $media->id, false)) !== null
            : PublicContent::videoLink($media->external_url) !== null));
        $description = $product->seo_description ?: PublicContent::text($product->description);
        $cover = $product->media->first(fn ($media) => $media->media_type->value === 'image' && $media->section->value === 'preview');
        $picture = $cover ? PublicContent::image($cover->disk_path, route('produk.media', $cover->id, false)) : null;
        $socialImage = $picture['url'] ?? null;
        $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name, 'description' => $description, 'url' => PublicContent::url('/produk/'.$product->slug)];

        return view('livewire.catalog.detail', compact('product'))->layout('components.layouts.app', ['title' => ($product->seo_title ?: $product->name).' — Trader Garuda', 'description' => $description, 'canonical' => '/produk/'.$product->slug, 'structuredData' => $schema, 'socialImage' => $socialImage]);
    }
}
