@props(['title' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="id" class="no-js">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <meta name="description" content="Trader Garuda — perangkat MetaTrader 5, panduan penggunaan, dan informasi produk.">
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
