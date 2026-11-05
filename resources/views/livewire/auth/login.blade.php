<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="login-title">
            <p class="eyebrow">AKUN TRADER GARUDA</p>
            <h1 id="login-title">Selamat<br>datang kembali<span>.</span></h1>
            <p>Masuk untuk mengakses akun dan layanan Trader Garuda Anda.</p>
            <div class="auth-intro-note">Akses admin dilindungi autentikasi dua faktor.</div>
        </section>
        <section class="auth-card" aria-labelledby="login-form-title">
            <p class="eyebrow">MASUK KE AKUN</p>
            <h2 id="login-form-title">Lanjutkan dari sini.</h2>
            @include('livewire.auth.partials.feedback')
            <form wire:submit="submit" class="auth-form">
                <x-auth-field name="email" label="Email" type="email" wire:model="email" autocomplete="username" maxlength="254" required autofocus />
                <x-auth-field name="password" label="Password" type="password" wire:model="password" autocomplete="current-password" required />
                <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Masuk <x-ui.icon name="arrow-right" /></span>
                    <span wire:loading wire:target="submit" role="status">Memeriksa akun…</span>
                </button>
            </form>
            <p class="auth-bottom-note">Belum memiliki akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
            <p class="auth-hint">Lupa password? Hubungi admin melalui kontak terdaftar Anda untuk pemeriksaan identitas manual.</p>
        </section>
    </main>
</div>
