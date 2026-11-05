<div><x-site-header /><main id="konten" class="shell section" tabindex="-1">
    <p class="eyebrow">KATALOG / MT5</p><h1>Tools, bukan janji.</h1><p class="section-description">Pelajari kegunaan, cara pakai dan hasil pengujian yang tersedia. Mengikuti broker IB bukan syarat membeli EA.</p>
    <div class="catalog-filters"><x-ui.editor-field model="search" label="Cari produk" type="search" maxlength="80" /><x-ui.editor-field model="type" label="Jenis produk" :options="['ea'=>'EA', 'indicator'=>'Indikator']" /><button class="auth-secondary" type="button" wire:click="$refresh" wire:loading.attr="disabled">Tampilkan produk</button><x-ui.loading target="search,type,$refresh" label="Memuat katalog…" /></div>
    <section aria-labelledby="catalog-title"><h2 id="catalog-title">Produk yang tersedia</h2><div class="catalog-grid">@forelse($products as $product)<x-product-card :product="$product" />@empty<x-ui.empty-state title="Belum ada produk tersedia">Informasi produk akan tampil setelah dipublikasikan. Tidak ada pembayaran yang dapat dilakukan saat ini.</x-ui.empty-state>@endforelse</div>{{ $products->links() }}</section>
    <x-risk-note />
</main><x-site-footer /></div>
