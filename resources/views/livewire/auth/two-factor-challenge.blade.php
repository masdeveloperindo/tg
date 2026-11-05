<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="challenge-title">
            <p class="eyebrow">VERIFIKASI ADMIN</p>
            <h1 id="challenge-title">Satu langkah<br>ke panel admin<span>.</span></h1>
            <p>Gunakan kode dari aplikasi authenticator Anda. Jika perangkat tidak tersedia, gunakan satu recovery code yang belum terpakai.</p>
        </section>
        <section class="auth-card" aria-labelledby="challenge-form-title">
            <p class="eyebrow">AUTENTIKASI DUA FAKTOR</p>
            <h2 id="challenge-form-title">Verifikasi akses.</h2>
            @include('livewire.auth.partials.feedback')
            <form wire:submit="submit" class="auth-form">
                <x-auth-field name="otp" label="Kode authenticator atau recovery code" wire:model="otp" autocomplete="one-time-code" maxlength="64" hint="Masukkan 6 digit authenticator atau satu recovery code. Setiap recovery code hanya dapat dipakai sekali." required autofocus />
                <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Verifikasi <x-ui.icon name="arrow-right" /></span>
                    <span wire:loading wire:target="submit" role="status">Memverifikasi akses…</span>
                </button>
            </form>
        </section>
    </main>
</div>
