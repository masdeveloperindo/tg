<div><x-site-header /><main id="konten" class="shell section product-detail" tabindex="-1">
    <p><a class="auth-text-link" href="{{ route('produk') }}">Katalog produk</a> / {{ $product->name }}</p>
    <p class="eyebrow">{{ $product->type->value === 'ea' ? 'EXPERT ADVISOR / MT5' : 'INDIKATOR / MT5' }}</p><h1>{{ $product->name }}</h1>
    <p class="auth-notice">Penjualan belum dibuka. Produk ini belum dapat dipesan.</p>
    @if($product->description)<section aria-label="Deskripsi produk" class="editor-prose">{!! \App\Support\SafeHtml::clean($product->description) !!}</section>@endif
    @php($preview = $product->media->where('section', \App\Enums\MediaSection::Preview))
    @php($tutorial = $product->media->where('section', \App\Enums\MediaSection::Tutorial))
    <nav class="detail-anchors" aria-label="Bagian produk">
        @if($preview->isNotEmpty())<a href="#preview">Preview</a>@endif
        @if($product->guides->contains(fn($guide) => $guide->steps->isNotEmpty()) || $tutorial->isNotEmpty())<a href="#cara-pakai">Cara pakai</a>@endif
        @if($product->performances->isNotEmpty())<a href="#performa">Track record</a>@endif
        @if($product->versions->isNotEmpty())<a href="#versi">Perubahan versi</a>@endif
        @if($product->faqs->isNotEmpty())<a href="#faq">FAQ</a>@endif
        @if($product->testimonials->isNotEmpty())<a href="#ulasan">Ulasan</a>@endif
    </nav>
    @if($preview->isNotEmpty())<section id="preview" tabindex="-1" class="section"><h2>Preview produk</h2><div class="catalog-grid">@foreach($preview as $media)<x-product-media :media="$media" />@endforeach</div></section>@endif
    @if($product->guides->contains(fn($guide) => $guide->steps->isNotEmpty()) || $tutorial->isNotEmpty())
        <section id="cara-pakai" tabindex="-1" class="section"><h2>Cara pakai</h2>
            @foreach($product->guides as $guide)<ol class="product-steps">@foreach($guide->steps as $step)<li><div class="editor-prose">{!! \App\Support\SafeHtml::clean($step->body) !!}</div>@php($image = \App\Support\PublicContent::image($step->image_path, route('produk.step.image', $step->id, false)))@if($image)<img src="{{ $image['url'] }}" alt="{{ $step->alt_text }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" loading="lazy">@endif</li>@endforeach</ol>@endforeach
            <div class="catalog-grid">@foreach($tutorial as $media)<x-product-media :media="$media" />@endforeach</div>
        </section>
    @endif
    @if($product->performances->isNotEmpty())<section id="performa" tabindex="-1" class="section"><h2>Track record</h2><p>Hasil historis bukan jaminan profit. Bandingkan jenis pengujian, kondisi dan periode sebelum mengambil keputusan.</p>
        @foreach($product->performances as $performance)<article class="catalog-card performance-card"><h3>{{ $performance->test_type->value === 'backtest' ? 'Backtest' : 'Forward test' }} · {{ $performance->account_environment->value === 'real' ? 'Akun real' : 'Akun demo' }}</h3><p>{{ $performance->symbol }} · {{ $performance->timeframe }} · {{ $performance->starts_on->format('d M Y') }} — {{ $performance->ends_on->format('d M Y') }}</p>
            <dl class="performance-metrics">@foreach(['net_profit_pct'=>'Profit bersih (%)','max_drawdown_pct'=>'Drawdown maksimum (%)','win_rate_pct'=>'Win rate (%)','profit_factor'=>'Profit factor','total_trades'=>'Jumlah transaksi'] as $field=>$label)@if($performance->{$field} !== null)<div><dt>{{ $label }}</dt><dd>{{ $performance->{$field} }}</dd></div>@endif @endforeach</dl>
            @php($image = \App\Support\PublicContent::image($performance->equity_image_path, route('produk.performance.image', $performance->id, false)))@if($image)<img src="{{ $image['url'] }}" alt="Equity hasil {{ $performance->test_type->value === 'backtest' ? 'backtest' : 'forward test' }} {{ $performance->symbol }}" width="{{ $image['width'] }}" height="{{ $image['height'] }}" loading="lazy">@endif
            <details><summary>Detail pengujian</summary><dl>@foreach(['broker_name'=>'Broker','initial_capital'=>'Modal awal','spread'=>'Spread','commission'=>'Komisi','leverage'=>'Leverage'] as $field=>$label)@if($performance->{$field} !== null && $performance->{$field} !== '')<div><dt>{{ $label }}</dt><dd>{{ $performance->{$field} }}</dd></div>@endif @endforeach</dl>@if($performance->version_id)<p>Versi produk: {{ $performance->version?->version }}</p>@endif @if($performance->methodology_note)<p>{{ $performance->methodology_note }}</p>@endif<p class="auth-hint">Diperbarui {{ $performance->updated_at->timezone('Asia/Jakarta')->format('d M Y') }} WIB.</p></details>
        </article>@endforeach
    </section>@endif
    @if($product->versions->isNotEmpty())<section id="versi" tabindex="-1" class="section"><h2>Perubahan versi</h2>@foreach($product->versions as $version)<article class="catalog-card"><h3>{{ $version->version }}</h3><p class="auth-hint">{{ $version->released_at->timezone('Asia/Jakarta')->format('d M Y') }}</p>@if($version->changelog)<p>{{ $version->changelog }}</p>@endif</article>@endforeach</section>@endif
    @if($product->faqs->isNotEmpty())<section id="faq" tabindex="-1" class="section"><h2>FAQ produk</h2>@foreach($product->faqs as $faq)<details class="catalog-card"><summary>{{ $faq->question }}</summary><div class="editor-prose">{!! \App\Support\SafeHtml::clean($faq->answer) !!}</div></details>@endforeach</section>@endif
    @if($product->testimonials->isNotEmpty())<section id="ulasan" tabindex="-1" class="section"><h2>Ulasan</h2>@foreach($product->testimonials as $testimonial)<figure class="catalog-card"><blockquote>{{ $testimonial->content }}</blockquote><figcaption>{{ $testimonial->display_name }}@if($testimonial->rating !== null) · {{ $testimonial->rating }}/5 @endif</figcaption></figure>@endforeach</section>@endif
    <x-risk-note />
</main><x-site-footer /></div>
