@if ($usePortal)
<div class="portal-form">
    <header class="portal-form-heading">
        <p class="eyebrow">KONFIRMASI AKSES ADMIN</p>
        <h1>Pastikan ini Anda.</h1>
        <p class="section-description">Tindakan sensitif memerlukan verifikasi password dan authenticator dalam 10 menit terakhir.</p>
    </header>
    @include('livewire.auth.partials.reauthenticate-form')
</div>
@else
<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="reauth-title">
            <p class="eyebrow">KONFIRMASI AKSES ADMIN</p>
            <h1 id="reauth-title">Pastikan<br>ini Anda<span>.</span></h1>
            <p>Tindakan sensitif memerlukan verifikasi password dan authenticator dalam 10 menit terakhir.</p>
        </section>
        @include('livewire.auth.partials.reauthenticate-form')
    </main>
</div>
@endif
