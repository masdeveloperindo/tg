<x-layouts.portal area="admin" title="Password sementara">
    <div class="portal-form">
        <h1>Kata sandi sementara dibuat.</h1>
        <p class="section-description">Hanya ditampilkan sekali. Sampaikan secara manual melalui kontak client yang sudah diverifikasi. Seluruh sesi lama telah dicabut; client wajib mengganti kata sandi setelah masuk.</p>
        <p class="private-code"><code>{{ $temporaryPassword }}</code></p>
        <a class="button button-lime" href="{{ route('admin.clients') }}">Kembali ke daftar client</a>
    </div>
</x-layouts.portal>
