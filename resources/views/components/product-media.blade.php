@props(['media'])
@php($picture = $media->media_type === \App\Enums\MediaType::Image ? \App\Support\PublicContent::image($media->disk_path, route('produk.media', $media->id, false)) : null)
@php($video = $media->media_type === \App\Enums\MediaType::VideoLink ? \App\Support\PublicContent::videoLink($media->external_url) : null)
@if($picture || $video)<figure class="catalog-card">@if($picture)<img src="{{ $picture['url'] }}" alt="{{ $media->alt_text }}" width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" loading="lazy">@else<a class="auth-secondary" href="{{ $video }}" target="_blank" rel="noopener noreferrer nofollow">{{ $media->alt_text ?: 'Buka video produk' }} <x-ui.icon /></a>@endif @if($media->caption)<figcaption>{{ $media->caption }}</figcaption>@endif</figure>@endif
