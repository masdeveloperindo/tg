<x-layouts.portal area="member" title="Ringkasan">
    <section class="portal-intro" aria-labelledby="member-welcome">
        <div class="portal-intro-copy">
            <p class="eyebrow">AKUN ANDA</p>
            <h1 id="member-welcome">Halo, {{ auth()->user()->name }}.</h1>
            <p>Akun Anda sudah aktif. Kelola keamanan akun dan pelajari informasi produk dari satu tempat.</p>
        </div>
        <x-ui.badge tone="success">Akun aktif</x-ui.badge>
    </section>
    @if(!empty($renewals))<x-ui.panel title="Perpanjang akses">@foreach($renewals as $renewal)<article><h2>{{ $renewal['name'] }}</h2><p>Akun MT5 {{ $renewal['mt5_account'] }} · {{ \App\Support\OrderDisplay::money($renewal['amount_idr']) }} · Akses sampai 31 Desember {{ $renewal['coverage_year'] }}</p><x-ui.button :href="route('checkout.slug', ['slug' => $renewal['slug'], 'renewal' => $renewal['license_id']])">Perpanjang</x-ui.button></article>@endforeach</x-ui.panel>@endif
    @if(\App\Models\TradingAccount::where('user_id',auth()->id())->exists())
        @include('livewire.rebates.balance',['balance'=>\App\Actions\Rebates\WalletTotals::read(auth()->id())])
        <x-ui.button :href="route('member.rebate')">Lihat hasil rebate</x-ui.button>
    @endif
    <div class="portal-grid">
        <x-ui.panel title="Layanan Anda" eyebrow="INFORMASI LAYANAN">
            <x-ui.empty-state title="Kelola produk dan akun Anda">
                <p>Lihat produk berlisensi atau ikuti program IB secara opsional. Ketersediaan penjualan mengikuti kesiapan layanan.</p>
                <x-slot:actions><x-ui.button :href="route('home').'#produk'">Lihat informasi produk <x-ui.icon /></x-ui.button></x-slot:actions>
            </x-ui.empty-state>
        </x-ui.panel>
        <x-ui.panel title="Keamanan akun" eyebrow="AKUN SAYA">
            <p>Gunakan kata sandi yang unik. Anda dapat mengganti kata sandi melalui profil akun.</p>
            <div class="portal-actions"><x-ui.button :href="route('member.profile')" variant="lime">Ganti kata sandi <x-ui.icon /></x-ui.button></div>
        </x-ui.panel>
    </div>
</x-layouts.portal>
