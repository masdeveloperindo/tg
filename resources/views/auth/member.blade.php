<x-layouts.portal area="member" title="Ringkasan">
    <section class="portal-intro" aria-labelledby="member-welcome">
        <div class="portal-intro-copy">
            <p class="eyebrow">AKUN ANDA</p>
            <h1 id="member-welcome">Halo, {{ auth()->user()->name }}.</h1>
            <p>Akun Anda sudah aktif. Kelola keamanan akun dan pelajari informasi produk dari satu tempat.</p>
        </div>
        <x-ui.badge tone="success">Akun aktif</x-ui.badge>
    </section>
    <div class="portal-grid">
        <x-ui.panel title="Layanan sedang disiapkan" eyebrow="INFORMASI LAYANAN">
            <x-ui.empty-state title="Pemesanan belum dibuka">
                <p>Pemesanan produk dan layanan rebate belum tersedia. Informasi berikutnya akan ditampilkan setelah layanan siap.</p>
                <x-slot:actions><x-ui.button :href="route('home').'#produk'">Lihat informasi produk <x-ui.icon /></x-ui.button></x-slot:actions>
            </x-ui.empty-state>
        </x-ui.panel>
        <x-ui.panel title="Keamanan akun" eyebrow="AKUN SAYA">
            <p>Gunakan kata sandi yang unik. Anda dapat mengganti kata sandi melalui profil akun.</p>
            <div class="portal-actions"><x-ui.button :href="route('member.profile')" variant="lime">Ganti kata sandi <x-ui.icon /></x-ui.button></div>
        </x-ui.panel>
    </div>
</x-layouts.portal>
