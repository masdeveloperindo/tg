<div x-data="{ tab: 'info', move(direction) { const tabs = [...$refs.tabs.querySelectorAll('[role=tab]')]; const next = (tabs.indexOf(document.activeElement) + direction + tabs.length) % tabs.length; tabs[next].click(); tabs[next].focus(); } }">
    <div class="auth-admin-heading"><div><p class="eyebrow">KATALOG / EDITOR PRODUK</p><h1>{{ $product ? 'Edit produk.' : 'Tambah produk.' }}</h1><p class="auth-muted">Satu editor untuk informasi, cara pakai, performa, file dan FAQ.</p></div><x-ui.button :href="route('admin.produk')">Daftar produk</x-ui.button></div>
    @include('livewire.auth.partials.feedback')
    <p class="auth-warning">Publikasi konten tidak membuka penjualan. Aktivasi lisensi tetap menunggu PoC EA dan kelengkapan konfigurasi bisnis.</p>
    @if($product)
        <div class="editor-status"><x-ui.badge>{{ ['draft'=>'Draft', 'published'=>'Dipublikasikan', 'archived'=>'Diarsipkan'][$product->status->value] }}</x-ui.badge><x-ui.button wire:click="confirm('published')">Publikasikan</x-ui.button><x-ui.button wire:click="confirm('draft')">Jadikan draft</x-ui.button><x-ui.button wire:click="confirm('archived')">Arsipkan</x-ui.button></div>
    @endif
    <div role="tablist" aria-label="Bagian editor produk" class="editor-tabs" x-ref="tabs" @keydown.arrow-right.prevent="move(1)" @keydown.arrow-left.prevent="move(-1)">
        @foreach(['info'=>'Informasi', 'preview'=>'Preview & cara pakai', 'performance'=>'Performa', 'versions'=>'File & versi', 'faq'=>'FAQ', 'price'=>'Harga', 'seo'=>'SEO'] as $key=>$label)
            <button id="tab-{{ $key }}" type="button" role="tab" aria-controls="panel-{{ $key }}" :aria-selected="tab === '{{ $key }}'" :tabindex="tab === '{{ $key }}' ? 0 : -1" @click="tab = '{{ $key }}'">{{ $label }}</button>
        @endforeach
    </div>
    <form wire:submit="save" class="auth-card auth-form" x-show="['info','price','seo'].includes(tab)">
        <section id="panel-info" role="tabpanel" aria-labelledby="tab-info" x-show="tab === 'info'">
            <h2>Informasi produk</h2>
            <x-ui.editor-field model="form.name" label="Nama produk" maxlength="180" />
            <x-ui.editor-field model="form.slug" label="Slug" maxlength="191" hint="Huruf kecil, angka dan tanda hubung. Slug yang sudah dipublikasikan tidak dapat diubah." />
            <x-ui.editor-field model="form.type" label="Tipe" :options="['ea'=>'EA', 'indicator'=>'Indikator']" />
            <x-ui.editor-field model="form.description" label="Deskripsi" type="textarea" maxlength="100000" hint="HTML sederhana: paragraf, heading h2–h4, daftar, strong/em dan tautan HTTPS. Script, style dan embed dibuang server." />
            @if($product?->description)<details><summary>Pratinjau deskripsi tersimpan</summary><div class="editor-prose">{!! \App\Support\SafeHtml::clean($product->description) !!}</div></details>@endif
        </section>
        <section id="panel-price" role="tabpanel" aria-labelledby="tab-price" x-show="tab === 'price'" x-cloak>
            <h2>Harga dasar</h2><p class="auth-hint">Gunakan nominal yang disetujui owner. Simulasi memakai harga produk tersimpan dan aturan aktif. Perubahan berlaku untuk kuotasi baru.</p>
            <x-ui.editor-field model="form.base_price_idr" label="Harga awal (IDR)" inputmode="decimal" />
            <x-ui.editor-field model="form.minimum_price_idr" label="Harga minimum (IDR)" inputmode="decimal" />
            @if($product)<x-ui.button wire:click="simulateCalendar">Simulasi harga 12 bulan</x-ui.button><ol>@foreach($calendarPrices as $month=>$amount)<li>Bulan {{ $month }}: {{ \App\Support\OrderDisplay::money($amount) }}</li>@endforeach</ol>@endif
        </section>
        <section id="panel-seo" role="tabpanel" aria-labelledby="tab-seo" x-show="tab === 'seo'" x-cloak>
            <h2>SEO produk</h2><x-ui.editor-field model="form.seo_title" label="Judul SEO (opsional)" maxlength="191" /><x-ui.editor-field model="form.seo_description" label="Deskripsi SEO (opsional)" type="textarea" maxlength="320" />
            @if($product)<p class="auth-hint">Pratinjau judul: {{ $product->seo_title ?: $product->name }} · /produk/{{ $product->slug }}</p>@endif
        </section>
        <button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="save">Simpan informasi, harga & SEO</button><x-ui.loading target="save" label="Menyimpan produk…" />
    </form>
    @unless($product)<p class="auth-notice" x-show="!['info','price','seo'].includes(tab)">Simpan informasi produk terlebih dahulu untuk menambah konten dan file.</p>@endunless
    @if($product)
        <section id="panel-preview" role="tabpanel" aria-labelledby="tab-preview" x-show="tab === 'preview'" x-cloak>
            <x-ui.panel title="Screenshot dan video">
                <form wire:submit="uploadMedia" class="auth-form">
                    @if(!isset($mediaForm['id']))<x-ui.upload-status field="image"><x-ui.editor-field model="image" label="Gambar (JPG/PNG/WebP, maksimal 2 MiB)" type="file" accept="image/jpeg,image/png,image/webp" /></x-ui.upload-status>
                    <x-ui.editor-field model="mediaForm.external_url" label="Atau tautan video HTTPS (YouTube/Vimeo)" type="url" />
                    @endif
                    <x-ui.editor-field model="mediaForm.section" label="Bagian" :options="['preview'=>'Preview', 'tutorial'=>'Cara pakai']" />
                    <x-ui.editor-field model="mediaForm.alt_text" label="Teks alternatif / deskripsi video" maxlength="255" />
                    <x-ui.editor-field model="mediaForm.caption" label="Keterangan (opsional)" maxlength="255" />
                    <x-ui.editor-field model="mediaForm.sort_order" label="Urutan" type="number" min="0" max="10000" />
                    <button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="uploadMedia,image">{{ isset($mediaForm['id']) ? 'Simpan keterangan media' : 'Tambah media' }}</button><x-ui.button wire:click="cancelContentEdit">Bersihkan form</x-ui.button><x-ui.loading target="uploadMedia" label="Memvalidasi dan menyimpan media…" />
                </form>
                <ul class="editor-media-list">@foreach($product->media->sortBy('sort_order') as $media)<li wire:key="media-{{ $media->id }}">
                    @if($media->media_type->value === 'image')<img src="{{ route('admin.produk.media', $media->id) }}" alt="{{ $media->alt_text }}" loading="lazy" width="320" height="200">@else<a href="{{ $media->external_url }}" target="_blank" rel="noopener noreferrer nofollow">{{ $media->alt_text }}</a>@endif
                    <p>{{ $media->caption }}</p><p class="auth-hint">{{ $media->section->value === 'preview' ? 'Preview' : 'Cara pakai' }} · urutan {{ $media->sort_order }}</p><x-ui.button wire:click="editMedia({{ $media->id }})">Edit keterangan</x-ui.button><x-ui.button wire:click="confirm('media', {{ $media->id }})">Hapus dari tampilan</x-ui.button>
                </li>@endforeach</ul>
            </x-ui.panel>
            <x-ui.panel title="Langkah cara pakai">
                <form wire:submit="saveTutorial" class="auth-form">
                    @foreach($steps as $index=>$step)<fieldset wire:key="step-{{ $index }}"><legend>Langkah {{ $index+1 }}</legend><x-ui.editor-field :model="'steps.'.$index.'.body'" label="Petunjuk" type="textarea" /><x-ui.editor-field :model="'steps.'.$index.'.media_id'" label="Gambar dari media produk (opsional)" :options="$product->media->where('media_type', \App\Enums\MediaType::Image)->pluck('alt_text','id')->all()" /><x-ui.button wire:click="removeStep({{ $index }})">Hapus langkah dari form</x-ui.button></fieldset>@endforeach
                    <x-ui.button wire:click="addStep">Tambah langkah</x-ui.button><button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="saveTutorial">Simpan panduan</button><x-ui.loading target="saveTutorial" label="Menyimpan panduan…" />
                </form>
            </x-ui.panel>
        </section>
        <section id="panel-performance" role="tabpanel" aria-labelledby="tab-performance" x-show="tab === 'performance'" x-cloak>
            <x-ui.panel title="Data pengujian">
                <p class="auth-warning">Isi hasil uji nyata. Kosongkan angka yang belum tersedia. Hasil historis bukan jaminan profit.</p>
                <form wire:submit="savePerformance" class="auth-form">
                    <x-ui.editor-field model="performanceForm.test_type" label="Jenis uji" :options="['backtest'=>'Backtest','forward_test'=>'Forward test']" />
                    <x-ui.editor-field model="performanceForm.account_environment" label="Lingkungan akun" :options="['demo'=>'Demo','real'=>'Real']" />
                    <x-ui.editor-field model="performanceForm.version_id" label="Versi produk (opsional)" :options="$product->versions->pluck('version','id')->all()" />
                    <div class="editor-field-grid">
                        <x-ui.editor-field model="performanceForm.starts_on" label="Awal pengujian" type="date" /><x-ui.editor-field model="performanceForm.ends_on" label="Akhir pengujian" type="date" />
                        @foreach(['symbol'=>'Pair / simbol','timeframe'=>'Timeframe','broker_name'=>'Broker','initial_capital'=>'Modal awal','spread'=>'Spread','commission'=>'Komisi','leverage'=>'Leverage','net_profit_pct'=>'Profit bersih (%)','max_drawdown_pct'=>'Drawdown maksimum (%)','win_rate_pct'=>'Win rate (%)','profit_factor'=>'Profit factor','total_trades'=>'Jumlah transaksi'] as $field=>$label)<x-ui.editor-field :model="'performanceForm.'.$field" :label="$label" />@endforeach
                    </div>
                    <x-ui.editor-field model="performanceForm.methodology_note" label="Metodologi / sumber hasil uji" type="textarea" />
                    <x-ui.upload-status field="reportFile"><x-ui.editor-field model="reportFile" label="Laporan asli PDF (opsional, maksimal 5 MiB)" type="file" accept="application/pdf" /></x-ui.upload-status>
                    <x-ui.upload-status field="equityImage"><x-ui.editor-field model="equityImage" label="Gambar equity (opsional, maksimal 2 MiB)" type="file" accept="image/jpeg,image/png,image/webp" /></x-ui.upload-status>
                    <button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="savePerformance,reportFile,equityImage">Simpan data pengujian</button><x-ui.loading target="savePerformance" label="Menyimpan data pengujian…" />
                </form>
                <ul class="auth-client-list">@foreach($product->performances as $performance)<li wire:key="performance-{{ $performance->id }}"><h3>{{ $performance->test_type->value === 'backtest' ? 'Backtest' : 'Forward test' }} · {{ $performance->account_environment->value }} · {{ $performance->symbol }}</h3><p>{{ $performance->starts_on->format('d M Y') }} — {{ $performance->ends_on->format('d M Y') }}</p><p class="auth-hint">Diperbarui {{ $performance->updated_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · editor #{{ $performance->updated_by }}</p>@if($performance->report_path)<a href="{{ route('admin.produk.report', $performance->id) }}">Unduh bukti PDF privat</a>@endif<div class="editor-status"><x-ui.button wire:click="editPerformance({{ $performance->id }})">Edit data uji</x-ui.button><x-ui.button wire:click="confirm('performances', {{ $performance->id }})">Hapus dari tampilan</x-ui.button></div></li>@endforeach</ul>
            </x-ui.panel>
        </section>
        <section id="panel-versions" role="tabpanel" aria-labelledby="tab-versions" x-show="tab === 'versions'" x-cloak>
            <x-ui.panel title="File dan versi EA">
                <form wire:submit="uploadVersion" class="auth-form">@if(!isset($versionForm['id']))<x-ui.editor-field model="versionForm.version" label="Nomor versi" maxlength="40" />@else<p>Edit keterangan versi {{ $versionForm['version'] }}</p>@endif<x-ui.editor-field model="versionForm.min_supported_version" label="Versi minimum yang didukung (opsional)" maxlength="40" /><x-ui.editor-field model="versionForm.changelog" label="Perubahan versi" type="textarea" />@if(!isset($versionForm['id']))<x-ui.upload-status field="versionFile"><x-ui.editor-field model="versionFile" label="File EX5 privat (maksimal 20 MiB)" type="file" accept=".ex5" /></x-ui.upload-status>@endif<button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="uploadVersion,versionFile">{{ isset($versionForm['id']) ? 'Simpan keterangan versi' : 'Unggah versi' }}</button><x-ui.button wire:click="cancelContentEdit">Bersihkan form</x-ui.button><x-ui.loading target="uploadVersion" label="Memeriksa file dan checksum…" /></form>
                <ul class="auth-client-list">@foreach($product->versions as $version)<li wire:key="version-{{ $version->id }}"><h3>{{ $version->version }} · {{ $version->is_active ? 'Aktif' : 'Tidak aktif' }}</h3><p>{{ $version->changelog }}</p><p class="auth-hint">{{ $version->bytes }} byte · SHA-256 {{ $version->sha256 }}</p><div class="editor-status"><x-ui.button wire:click="editVersion({{ $version->id }})">Edit keterangan versi</x-ui.button><a class="auth-secondary" href="{{ route('admin.produk.version', $version->id) }}">Unduh file privat</a>@unless($version->is_active)<x-ui.button wire:click="confirm('activate', {{ $version->id }})">Aktifkan versi</x-ui.button>@endunless</div></li>@endforeach</ul>
            </x-ui.panel>
        </section>
        <section id="panel-faq" role="tabpanel" aria-labelledby="tab-faq" x-show="tab === 'faq'" x-cloak>
            <x-ui.panel title="FAQ produk"><form wire:submit="saveFaq" class="auth-form"><x-ui.editor-field model="faqForm.question" label="Pertanyaan" maxlength="500" /><x-ui.editor-field model="faqForm.answer" label="Jawaban" type="textarea" /><x-ui.editor-field model="faqForm.sort_order" label="Urutan" type="number" min="0" max="10000" /><button class="button button-lime" type="submit" wire:loading.attr="disabled" wire:target="saveFaq">Simpan FAQ</button><x-ui.loading target="saveFaq" label="Menyimpan FAQ…" /></form><ul class="auth-client-list">@foreach($product->faqs->sortBy('sort_order') as $faq)<li wire:key="faq-{{ $faq->id }}"><h3>{{ $faq->question }}</h3><p>{{ strip_tags($faq->answer) }}</p><div class="editor-status"><x-ui.button wire:click="editFaq({{ $faq->id }})">Edit FAQ</x-ui.button><x-ui.button wire:click="confirm('faqs', {{ $faq->id }})">Hapus FAQ</x-ui.button></div></li>@endforeach</ul></x-ui.panel>
        </section>
    @endif
    @if($confirmation !== null)
        <x-ui.modal id="product-confirm" title="Konfirmasi perubahan produk" description="Perubahan akan disimpan dan dicatat dalam audit. Publikasi dan versi aktif tidak mengaktifkan penjualan." wire:loading.class="auth-is-busy" wire:target="execute,cancelConfirmation" x-on:modal-cancel="$wire.cancelConfirmation()">
            <p>{{ ['published'=>'Publikasikan konten produk?', 'draft'=>'Sembunyikan produk dari publik?', 'archived'=>'Arsipkan produk? Data historis tetap disimpan.', 'activate'=>'Jadikan versi ini satu-satunya versi aktif?', 'media'=>'Hapus media dari tampilan produk?', 'faqs'=>'Hapus FAQ produk?', 'performances'=>'Hapus hasil uji dari tampilan? Bukti asli tetap disimpan.'][$confirmation] ?? 'Periksa perubahan sebelum melanjutkan.' }}</p>
            @include('livewire.auth.partials.feedback')
            <div class="auth-modal-actions"><button class="auth-secondary" type="button" wire:click="cancelConfirmation" wire:loading.attr="disabled" wire:target="execute,cancelConfirmation">Batal</button><button class="button button-lime" type="button" wire:click="execute" wire:loading.attr="disabled" wire:target="execute,cancelConfirmation">Konfirmasi</button><x-ui.loading target="execute" label="Menyimpan perubahan…" /></div>
        </x-ui.modal>
    @endif
</div>
