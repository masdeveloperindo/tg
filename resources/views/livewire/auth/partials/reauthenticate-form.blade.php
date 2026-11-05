<section class="auth-card" aria-labelledby="reauth-form-title">
    <p class="eyebrow">VERIFIKASI ULANG</p>
    <h2 id="reauth-form-title">Konfirmasi identitas.</h2>
    @include('livewire.auth.partials.feedback')
    <form wire:submit="submit" class="auth-form">
        <x-auth-field name="password" label="Password saat ini" type="password" wire:model="password" autocomplete="current-password" required autofocus />
        <x-auth-field name="otp" label="Kode authenticator" wire:model="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required />
        <button class="button button-lime auth-submit" type="submit" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">Verifikasi dan lanjutkan <x-ui.icon name="arrow-right" /></span>
            <span wire:loading wire:target="submit" role="status">Memeriksa identitas…</span>
        </button>
    </form>
    <p class="auth-bottom-note"><a href="{{ route('admin') }}">Kembali ke panel admin</a></p>
</section>
