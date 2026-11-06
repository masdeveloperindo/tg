@props(['area' => 'member', 'title' => 'Ringkasan', 'eyebrow' => null])

@php
    $isAdmin = $area === 'admin';
    $areaName = $isAdmin ? 'Panel admin' : 'Area client';
    $navigation = $isAdmin
        ? [
            ['label' => 'Urusan hari ini', 'items' => [['route' => 'admin', 'label' => 'Ringkasan', 'active' => 'admin']]],
            ['label' => 'Penjualan', 'items' => [['route'=>'admin.pesanan','label'=>'Pesanan','active'=>'admin.pesanan*'],['route'=>'admin.lisensi','label'=>'Lisensi','active'=>'admin.lisensi*']]],
            ['label' => 'IB & rebate', 'items' => [['route'=>'admin.broker','label'=>'Broker','active'=>'admin.broker'],['route'=>'admin.rebate.akun','label'=>'Verifikasi IB','active'=>'admin.rebate.akun'],['route'=>'admin.rebate','label'=>'Rebate','active'=>'admin.rebate'],['route'=>'admin.penarikan','label'=>'Pencairan','active'=>'admin.penarikan*']]],
            ['label' => 'Katalog', 'items' => [['route' => 'admin.produk', 'label' => 'Produk', 'active' => 'admin.produk*']]],
            ['label' => 'Konten & operasional', 'items' => array_merge(array_map(fn ($kind)=>['route'=>'admin.'.$kind,'label'=>ucfirst($kind),'active'=>'admin.'.$kind.'*'],array_keys(\App\Actions\Content\Types::MODELS)),[['route'=>'admin.anomali','label'=>'Anomali','active'=>'admin.anomali*'],['route'=>'admin.pengaturan','label'=>'Pengaturan','active'=>'admin.pengaturan'],['route'=>'admin.log','label'=>'Audit','active'=>'admin.log']])],
            ['label' => 'Kelola akses', 'items' => [['route' => 'admin.clients', 'label' => 'Client', 'active' => 'admin.clients*']]],
            ['label' => 'Akun & keamanan', 'items' => [['route' => 'admin.reauth', 'label' => 'Verifikasi ulang akses', 'active' => 'admin.reauth'], ['route' => 'password.change', 'label' => 'Ganti kata sandi', 'active' => 'password.change']]],
        ]
        : [
            ['label' => 'Area saya', 'items' => [['route' => 'member', 'label' => 'Ringkasan', 'active' => 'member']]],
            ['label' => 'Produk', 'items' => [['route' => 'member.produk', 'label' => 'Produk Saya', 'active' => 'member.produk*']]],
            ['label'=>'Berkas','items'=>[['route'=>'member.unduhan','label'=>'Unduhan','active'=>'member.unduhan']]],
            ['label' => 'Pesanan', 'items' => [['route' => 'member.pesanan', 'label' => 'Pesanan saya', 'active' => ['member.pesanan', 'pesanan.kode']]]],
            ['label' => 'Rebate opsional', 'items' => [['route'=>'member.rebate.akun','label'=>'Broker IB Saya','active'=>'member.rebate.akun'],['route'=>'member.rebate','label'=>'Hasil Rebate','active'=>'member.rebate'],['route'=>'member.penarikan','label'=>'Pencairan','active'=>'member.penarikan']]],
            ['label' => 'Akun', 'items' => [['route' => 'member.profile', 'label' => 'Profil & kata sandi', 'active' => ['member.profile', 'password.change']]]],
        ];
@endphp

<x-layouts.app :title="$title.' — Trader Garuda'" :noindex="true">
    <div class="portal-shell" x-data="tgNavigation()" @keydown.escape.window="close()">
        <aside id="portal-navigation" class="portal-sidebar" data-navigation data-drawer :class="{ 'is-open': mobileOpen }" aria-label="Navigasi {{ $areaName }}">
            <div class="portal-sidebar-heading">
                <x-brand inverse />
                <button type="button" class="nav-close" data-nav-close @click.stop="close()" aria-label="Tutup menu"><x-ui.icon name="close" /></button>
            </div>
            <div class="portal-sidebar-context">
                <p class="eyebrow">{{ $isAdmin ? 'GARUDA / OPERATIONS' : 'GARUDA / MEMBER HUB' }}</p>
                <p class="portal-sidebar-title">{{ $areaName }}</p>
            </div>
            <nav class="portal-nav" aria-label="Menu {{ $areaName }}" @click="close(false)">
                @foreach($navigation as $group)
                    <div class="portal-nav-group">
                        <p class="portal-nav-label">{{ $group['label'] }}</p>
                        @foreach($group['items'] as $item)
                            <a class="portal-nav-link" href="{{ route($item['route']) }}" @if(request()->routeIs(...(array) $item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                @endforeach
                <div class="portal-nav-group">
                    <p class="portal-nav-label">Trader Garuda</p>
                    <a class="portal-nav-link" href="{{ route('home') }}">Kembali ke beranda <x-ui.icon /></a>
                </div>
            </nav>
            <form class="portal-logout" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="portal-nav-link" type="submit">Keluar dari akun</button>
            </form>
        </aside>
        <button class="nav-scrim" type="button" x-show="mobileOpen" x-cloak @click="close()" aria-label="Tutup menu" tabindex="-1"></button>
        <div class="portal-workspace" :inert="mobileOpen">
            <header class="portal-header">
                <div class="portal-header-context"><p>{{ $eyebrow ?? $areaName }}</p><strong>{{ $title }}</strong></div>
                <button class="nav-toggle" type="button" data-nav-toggle aria-controls="portal-navigation" aria-expanded="false" :aria-expanded="mobileOpen" @click="toggle()"><x-ui.icon name="menu" /> Menu</button>
            </header>
            <main id="konten" class="portal-content" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.app>
