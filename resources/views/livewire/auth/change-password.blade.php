@if ($usePortal)
<div class="portal-form">
    <header class="portal-form-heading">
        <p class="eyebrow">KEAMANAN AKUN</p>
        <h1>Password baru. Akses terjaga.</h1>
        <p class="section-description">Gunakan password unik yang tidak dipakai pada akun lain. Perubahan ini memutus seluruh sesi lama.</p>
    </header>
    @include('livewire.auth.partials.change-password-form')
</div>
@else
<div class="auth-page">
    @include('livewire.auth.partials.header')
    <main id="konten" tabindex="-1" class="auth-layout shell">
        <section class="auth-intro" aria-labelledby="password-title">
            <p class="eyebrow">KEAMANAN AKUN</p>
            <h1 id="password-title">Password baru.<br>Akses terjaga<span>.</span></h1>
            <p>Gunakan password unik yang tidak dipakai pada akun lain. Perubahan ini memutus seluruh sesi lama.</p>
        </section>
        @include('livewire.auth.partials.change-password-form')
    </main>
</div>
@endif
