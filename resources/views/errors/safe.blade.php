<x-layouts.app :title="$status.' — Trader Garuda'" :noindex="true">
    <header class="shell"><x-brand /></header>
    <main id="konten" class="shell auth-page" tabindex="-1">
        <section class="auth-card"><h1>{{ $status }}</h1><p>{{ $message }}</p>
        <p class="auth-hint">Referensi permintaan: {{ $id }}</p>
        <a class="auth-secondary" href="{{ route('home') }}">Kembali ke beranda</a></section>
    </main>
</x-layouts.app>
