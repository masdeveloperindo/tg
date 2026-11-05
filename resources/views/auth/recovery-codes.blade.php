<x-layouts.portal area="admin" title="Kode pemulihan">
    <div class="portal-form">
        <h1>Simpan kode pemulihan.</h1>
        <p class="section-description">Kode ini hanya ditampilkan sekali. Simpan di tempat pribadi yang aman. Setiap kode dapat dipakai sekali jika aplikasi authenticator tidak tersedia. Jangan kirim kode melalui chat.</p>
        <ul class="recovery-list">@foreach ($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        <a class="button button-lime" href="{{ route('admin') }}">Sudah disimpan, lanjut ke admin</a>
    </div>
</x-layouts.portal>
