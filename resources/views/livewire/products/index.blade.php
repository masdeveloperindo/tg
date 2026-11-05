<div>
    <div class="auth-admin-heading"><div><p class="eyebrow">KATALOG / ADMIN</p><h1>Produk.</h1><p class="auth-muted">Kelola konten produk dari satu editor. Publikasi tidak mengaktifkan penjualan.</p></div><x-ui.button :href="route('admin.produk.create')" variant="lime">Tambah produk</x-ui.button></div>
    <x-ui.panel title="Daftar produk">
        <x-auth-field name="search" label="Cari produk" type="search" wire:model.live.debounce.350ms="search" maxlength="120" />
        <x-ui.loading target="search" label="Mencari produk…" />
        <ul class="auth-client-list">
            @forelse($products as $product)
                <li class="auth-client-row" wire:key="product-{{ $product->id }}"><div><h3>{{ $product->name }}</h3><p>{{ $product->type->value === 'ea' ? 'EA' : 'Indikator' }} · {{ ['draft' => 'Draft', 'published' => 'Dipublikasikan', 'archived' => 'Diarsipkan'][$product->status->value] }}</p><p class="auth-hint">Penjualan nonaktif</p></div><x-ui.button :href="route('admin.produk.id.edit', $product->id)">Edit produk</x-ui.button></li>
            @empty
                <li><x-ui.empty-state title="Belum ada produk">Tambahkan hanya produk dan informasi yang dapat diverifikasi.</x-ui.empty-state></li>
            @endforelse
        </ul>
        {{ $products->links() }}
    </x-ui.panel>
</div>
