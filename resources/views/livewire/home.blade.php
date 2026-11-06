<div>
    <div class="top-strip">
        <div class="shell top-strip-inner">
            <span>TRADER GARUDA / MT5 TOOLKIT</span>
            <span>INFORMASI JELAS. PILIHAN SADAR.</span>
        </div>
    </div>

    <x-site-header />

    <main id="konten" tabindex="-1">
        <section class="hero" aria-labelledby="hero-title">
            <div class="shell hero-inner">
                <div>
                    <p class="eyebrow">INDEPENDENT MT5 TOOLKIT</p>
                    <h1 id="hero-title">TRADING DENGAN<br><span>STRUKTUR.</span></h1>
                    <p class="hero-description">Kenali perangkat MetaTrader 5, pelajari cara penggunaan dan risikonya, lalu tentukan pilihan sesuai kebutuhan Anda.</p>
                    <a class="button button-lime" href="#produk">Lihat informasi produk <x-ui.icon /></a>
                </div>
                <aside class="hero-note" aria-labelledby="preparation-title">
                    <span class="eyebrow">STATUS LAYANAN</span>
                    <h2 id="preparation-title">Pemesanan<br>belum dibuka.</h2>
                    <p>Informasi produk dan layanan sedang disiapkan. Belum ada produk yang dapat dipesan atau pembayaran yang dapat dilakukan melalui halaman ini.</p>
                    <span class="status-label">DALAM PERSIAPAN</span>
                </aside>
            </div>
        </section>

        <section id="produk" tabindex="-1" class="shell section" aria-labelledby="products-title">
            <p class="eyebrow">KATALOG / DIGITAL PRODUCTS</p>
            <h2 id="products-title">TOOLS, BUKAN JANJI.</h2>
            <p class="section-description">Detail produk akan menyatukan preview, panduan penggunaan, versi, dan catatan pengujian yang tersedia.</p>
            @if($products->isNotEmpty())<div class="catalog-grid">@foreach($products as $product)<x-product-card :product="$product" />@endforeach</div><p><a class="auth-secondary" href="{{ route('produk') }}">Lihat seluruh produk</a></p>@else
            <div class="empty-state">
                <span class="empty-symbol" aria-hidden="true"><x-ui.icon /></span>
                <div>
                    <h3>Belum ada produk tersedia</h3>
                    <p>Daftar produk akan ditampilkan setelah siap ditawarkan. Periksa kembali halaman ini untuk informasi berikutnya.</p>
                </div>
            </div>
            @endif
        </section>

        <section id="alur" tabindex="-1" class="shell section steps-section" aria-labelledby="steps-title">
            <p class="eyebrow">PANDUAN / CARA MEMULAI</p>
            <h2 id="steps-title">PAHAMI SEBELUM MEMILIH.</h2>
            <p class="section-description">Alur berikut berlaku setelah pemesanan dibuka.</p>
            <ol class="steps">
                <li>
                    <span class="step-number" aria-hidden="true">01</span>
                    <h3>Pelajari produk</h3>
                    <p>Baca manfaat, cara pakai, dan risiko. Mengikuti program broker IB bersifat opsional.</p>
                </li>
                <li>
                    <span class="step-number" aria-hidden="true">02</span>
                    <h3>Pembayaran manual</h3>
                    <p>Ikuti rincian pesanan dan kirim bukti melalui chat Telegram pribadi admin. Admin memeriksa dana masuk.</p>
                </li>
                <li>
                    <span class="step-number" aria-hidden="true">03</span>
                    <h3>Akses setelah konfirmasi</h3>
                    <p>Lisensi dan unduhan tersedia setelah admin mengonfirmasi pembayaran di website.</p>
                </li>
            </ol>
            <aside class="risk-note" aria-label="Informasi risiko trading">
                <strong>Pahami risikonya.</strong> Trading memiliki risiko kerugian. Penggunaan perangkat trading tidak menjamin keuntungan atau hasil tertentu.
            </aside>
        </section>
        @if($testimonials->isNotEmpty())<section class="shell section"><h2>Pengalaman pengguna</h2><div class="catalog-grid">@foreach($testimonials as $testimonial)<article class="catalog-card">@if($testimonial->photo_path)<img src="{{ route('content.image',['type'=>'testimonial','id'=>$testimonial->id]) }}" alt="{{ $testimonial->display_name }}" width="80" height="80">@endif<h3>{{ $testimonial->display_name }}</h3><p>{{ $testimonial->content }}</p>@if($testimonial->rating)<p>Rating {{ $testimonial->rating }}/5</p>@endif</article>@endforeach</div></section>@endif
    </main>

    <x-site-footer />
</div>
