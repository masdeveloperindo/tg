@props(['inverse' => false])

<a href="{{ route('home') }}" aria-label="Trader Garuda — beranda" {{ $attributes->class(['brand', 'brand-inverse' => $inverse]) }}>
    <span class="brand-mark" aria-hidden="true"></span>
    <span>TRADER<br>GARUDA<span class="brand-dot">.</span></span>
</a>
