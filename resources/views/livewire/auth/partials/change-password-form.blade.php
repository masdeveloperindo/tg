<section class="auth-card" aria-labelledby="password-form-title">
    <p class="eyebrow">GANTI PASSWORD</p>
    <h2 id="password-form-title">Perbarui akses Anda.</h2>
    @if (auth()->user()?->must_change_password)
        <p class="auth-warning">Akun Anda memakai password sementara. Ganti password untuk melanjutkan.</p>
    @endif
    @include('livewire.auth.partials.feedback')
    <form wire:submit="submit" class="auth-form">
        <x-auth-field name="current_password" label="Password saat ini" type="password" wire:model="current_password" autocomplete="current-password" required autofocus />
        <x-auth-field name="password" label="Password baru" type="password" wire:model="password" autocomplete="new-password" minlength="12" maxlength="128" hint="Gunakan password unik dengan panjang 12–128 karakter." required />
        <x-auth-field name="password_confirmation" label="Ulangi password baru" type="password" wire:model="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required />
        <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">Simpan password baru <x-ui.icon name="arrow-right" /></span>
            <span wire:loading wire:target="submit" role="status">Memperbarui password…</span>
        </button>
    </form>
</section>
