<x-layouts.portal area="admin" title="Ringkasan">
    <section class="portal-intro" aria-labelledby="admin-welcome">
        <div class="portal-intro-copy">
            <p class="eyebrow">AKSES ADMIN</p>
            <h1 id="admin-welcome">Akses terverifikasi.</h1>
            <p>Periksa tugas yang membutuhkan keputusan manual dari data server.</p>
        </div>
        <x-ui.badge tone="success">Akses terverifikasi</x-ui.badge>
    </section>
    <section class="portal-grid" aria-label="Tugas Hari Ini">@foreach(\App\Actions\Operations\Tasks::cards() as $task)<x-ui.panel :title="$task['label']"><p><strong>{{ $task['count'] }}</strong> tugas</p><x-ui.button :href="$task['url']">Buka tugas</x-ui.button></x-ui.panel>@endforeach</section>
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
