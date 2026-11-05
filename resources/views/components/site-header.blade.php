@props(['compact' => false])

<header class="site-header" x-data="tgNavigation()" @keydown.escape.window="close()">
    <div class="shell header-inner">
        <x-brand />
        <button class="nav-toggle" type="button" data-nav-toggle aria-controls="site-navigation" aria-expanded="false" :aria-expanded="mobileOpen" @click="toggle()">
            <x-ui.icon name="menu" /> Menu
        </button>
        <nav id="site-navigation" class="site-navigation" data-navigation :class="{ 'is-open': mobileOpen }" aria-label="Navigasi utama" @click="close(false)">
            <div class="site-nav-links">
                <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                @unless($compact)
                    <a href="{{ route('home') }}#produk">Produk MT5</a>
                    <a href="{{ route('home') }}#alur">Cara memulai</a>
                @endunless
            </div>
            <div class="site-nav-actions">
                @guest
                    <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Masuk</a>
                    <x-ui.button :href="route('register')" variant="lime" :aria-current="request()->routeIs('register') ? 'page' : null">Daftar</x-ui.button>
                @else
                    <a href="{{ route(auth()->user()->role === \App\Enums\UserRole::Admin ? 'admin' : 'member') }}">Akun saya</a>
                    @if($compact)
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="quiet">Keluar</x-ui.button>
                        </form>
                    @endif
                @endguest
            </div>
        </nav>
    </div>
</header>
