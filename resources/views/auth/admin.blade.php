<x-layouts.portal area="admin" title="Ringkasan">
    <section class="portal-intro" aria-labelledby="admin-welcome">
        <div class="portal-intro-copy">
            <p class="eyebrow">AKSES ADMIN</p>
            <h1 id="admin-welcome">Akses terverifikasi.</h1>
            <p>Kelola akses client dari kontak terdaftar. Fitur transaksi belum tersedia.</p>
        </div>
        <x-ui.badge tone="success">Akses terverifikasi</x-ui.badge>
    </section>
    <div class="portal-grid">
        <x-ui.panel title="Kelola client" eyebrow="KELOLA AKSES">
            <p>Cari akun client terdaftar dan bantu pemulihan akses sesuai kewenangan admin.</p>
            <div class="portal-actions"><x-ui.button :href="route('admin.clients')" variant="lime">Kelola client <x-ui.icon /></x-ui.button></div>
        </x-ui.panel>
        <x-ui.panel title="Keamanan akun" eyebrow="AKUN ADMIN">
            <p>Verifikasi ulang akses ketika diperlukan untuk tindakan sensitif dan kelola kata sandi akun Anda.</p>
            <div class="portal-actions">
                <x-ui.button :href="route('admin.reauth')">Verifikasi ulang akses</x-ui.button>
                <x-ui.button :href="route('password.change')" variant="quiet">Ganti kata sandi</x-ui.button>
            </div>
        </x-ui.panel>
    </div>
</x-layouts.portal>
