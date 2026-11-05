@props(['product'])
@php($cover = $product->media->firstWhere('section', \App\Enums\MediaSection::Preview))
@php($picture = $cover ? \App\Support\PublicContent::image($cover->disk_path, route('produk.media', $cover->id, false)) : null)
<article class="catalog-card">
    @if($picture)<img src="{{ $picture['url'] }}" alt="{{ $cover->alt_text }}" width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" loading="lazy">@endif
    <p class="eyebrow">{{ $product->type->value === 'ea' ? 'EXPERT ADVISOR' : 'INDIKATOR' }}</p><h3><a href="{{ route('produk.slug', $product->slug) }}">{{ $product->name }}</a></h3>
    @if($product->description)<p>{{ \App\Support\PublicContent::text($product->description, 170) }}</p>@endif
    <x-product-price :product="$product" /><a class="auth-secondary" href="{{ route('produk.slug', $product->slug) }}">Lihat detail <x-ui.icon name="arrow-right" /></a>
</article>
