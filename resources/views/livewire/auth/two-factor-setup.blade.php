<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="setup-title">
            <p class="eyebrow">KEAMANAN ADMIN</p>
            <h1 id="setup-title">Tambahkan<br>lapisan aman<span>.</span></h1>
            <p>Aktifkan autentikasi dua faktor dengan aplikasi authenticator sebelum membuka panel admin.</p>
            <div class="auth-intro-note">Jangan bagikan secret, kode authenticator, atau recovery code melalui chat.</div>
        </section>
        <section class="auth-card" aria-labelledby="setup-form-title">
            <p class="eyebrow">PENDAFTARAN AUTHENTICATOR MANUAL</p>
            <h2 id="setup-form-title">Hubungkan authenticator.</h2>
            @include('livewire.auth.partials.feedback')
            @if ($setupSecret)
                <ol class="auth-steps">
                    <li>Buka aplikasi authenticator dan tambahkan akun secara manual.</li>
                    <li>Pilih kode berbasis waktu (TOTP), lalu gunakan akun dan secret berikut.</li>
                    <li>Masukkan kode 6 digit yang sedang ditampilkan aplikasi.</li>
                </ol>
                <dl class="auth-secret-box">
                    <dt>Nama layanan</dt><dd>Trader Garuda</dd>
                    <dt>Akun</dt><dd>{{ $accountEmail }}</dd>
                    <dt>Secret penyiapan</dt><dd class="auth-secret">{{ $setupSecret }}</dd>
                </dl>
                <form wire:submit="submit" class="auth-form">
                    <x-auth-field name="otp" label="Kode authenticator" wire:model="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required />
                    <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Aktifkan dua faktor <x-ui.icon name="arrow-right" /></span>
                        <span wire:loading wire:target="submit" role="status">Memverifikasi kode…</span>
                    </button>
                </form>
            @else
                <p class="auth-warning" role="status">Waktu penyiapan telah habis. Masuk kembali untuk memulai penyiapan baru.</p>
            @endif
            <p class="auth-hint">Setelah berhasil, simpan 10 recovery code yang hanya ditampilkan satu kali.</p>
        </section>
    </main>
</div>
