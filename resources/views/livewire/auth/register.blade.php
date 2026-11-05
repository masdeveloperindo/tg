<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="register-title">
            <p class="eyebrow">MULAI DARI AKUN ANDA</p>
            <h1 id="register-title">Siap untuk<br>langkah berikut<span>?</span></h1>
            <p>Gunakan email dan kontak yang dapat Anda akses. Kontak terdaftar membantu admin memverifikasi permintaan pemulihan akun.</p>
            <div class="auth-intro-note">Registrasi client tidak memerlukan OTP atau verifikasi email.</div>
        </section>
        <section class="auth-card" aria-labelledby="register-form-title">
            <p class="eyebrow">REGISTRASI CLIENT</p>
            <h2 id="register-form-title">Buat akun Anda.</h2>
            @include('livewire.auth.partials.feedback')
            <form wire:submit="submit" class="auth-form">
                <x-auth-field name="name" label="Nama" wire:model="name" autocomplete="name" maxlength="120" required autofocus />
                <x-auth-field name="email" label="Email" type="email" wire:model="email" autocomplete="email" maxlength="191" required />
                <div class="auth-field">
                    <label for="contact_channel">Jenis kontak</label>
                    <select id="contact_channel" name="contact_channel" class="auth-input" wire:model="contact_channel" required aria-invalid="{{ $errors->has('contact_channel') ? 'true' : 'false' }}" @error('contact_channel') aria-describedby="contact_channel-error" @enderror>
                        <option value="telegram">Telegram</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                    @error('contact_channel')<p id="contact_channel-error" class="auth-error">{{ $message }}</p>@enderror
                </div>
                <x-auth-field name="contact_value" label="Username Telegram atau nomor WhatsApp" wire:model="contact_value" autocomplete="off" maxlength="32" hint="Telegram: username aktif Anda. WhatsApp: nomor dengan kode negara, misalnya +62." required />
                <x-auth-field name="password" label="Password" type="password" wire:model="password" autocomplete="new-password" minlength="12" maxlength="128" hint="Gunakan password unik dengan panjang 12–128 karakter." required />
                <x-auth-field name="password_confirmation" label="Ulangi password" type="password" wire:model="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required />
                <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Buat akun <x-ui.icon name="arrow-right" /></span>
                    <span wire:loading wire:target="submit" role="status">Membuat akun…</span>
                </button>
            </form>
            <p class="auth-bottom-note">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
        </section>
    </main>
</div>
