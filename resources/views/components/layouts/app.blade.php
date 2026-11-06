@props(['title' => null, 'noindex' => false, 'description' => null, 'canonical' => null, 'structuredData' => null, 'socialImage' => null])
@php($site=\App\Actions\Operations\Configuration::json('public.site'))
@php($description=$description??$site['description']??'Trader Garuda — perangkat MetaTrader 5, panduan penggunaan, dan informasi produk.')
@php($title=$title??$site['title']??config('app.name','Trader Garuda'))
<!DOCTYPE html>
<html lang="id" class="no-js">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="description" content="{{ $description }}">
        @if($canonical)
            <link rel="canonical" href="{{ \App\Support\PublicContent::url($canonical) }}">
            <meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}"><meta property="og:url" content="{{ \App\Support\PublicContent::url($canonical) }}"><meta property="og:type" content="website"><meta property="og:locale" content="id_ID"><meta name="twitter:card" content="summary">
        @endif
        @if($socialImage)<meta property="og:image" content="{{ $socialImage }}"><meta name="twitter:image" content="{{ $socialImage }}">@endif
        @if($structuredData)<script type="application/ld+json">{!! json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>@endif
        <title>{{ $title ?? config('app.name', 'Trader Garuda') }}</title>
        @if ($noindex ?? false)
            <meta name="robots" content="noindex, nofollow">
        @endif
        @include('components.navigation-script')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body>
        <a class="skip-link" href="#konten">Langsung ke konten</a>
        {{ $slot }}
        <x-ui.request-feedback />
        @livewireScripts
    </body>
</html>
