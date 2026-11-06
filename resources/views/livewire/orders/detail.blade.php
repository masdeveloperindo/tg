<div x-data="{ copied: false, copyError: false, async copyDetails() { this.copied = false; this.copyError = false; try { await navigator.clipboard.writeText(this.$refs.orderText.value); this.copied = true; } catch { this.copyError = true; this.$refs.orderText.focus(); this.$refs.orderText.select(); } } }">
    <p class="eyebrow">PESANAN / PEMBAYARAN MANUAL</p><h1>{{ $order->code }}</h1>
    <p role="status"><strong>{{ \App\Support\OrderDisplay::label($order) }}</strong></p>
    <x-ui.panel title="Rincian pesanan"><dl><dt>Produk</dt><dd>{{ $order->checkoutQuote?->pricing_snapshot['product_name'] ?? $order->product->name }}</dd><dt>Nominal pasti</dt><dd>{{ \App\Support\OrderDisplay::money($order->price_locked_idr) }}</dd><dt>Masa akses</dt><dd>Sampai 31 Desember {{ $order->coverage_year }}, 23:59:59 WIB</dd><dt>Akun MT5</dt><dd>{{ $order->mt5_account }} · {{ $order->mt5_server }} · {{ $order->account_type->value }}</dd><dt>Batas pembayaran</dt><dd>{{ $order->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</dd></dl><x-ui.button wire:click="$refresh" wire:loading.attr="disabled">Cek Status</x-ui.button><x-ui.loading target="$refresh" label="Memeriksa status terbaru…" /></x-ui.panel>
    @if(\App\Support\OrderDisplay::status($order) === 'waiting')
        <x-ui.panel title="Transfer manual">@if($payment)<p><strong>{{ $payment['label'] }}</strong><br>{{ $payment['account_number'] }}<br>Atas nama {{ $payment['account_holder'] }}</p>@if(!empty($payment['instructions']))<p>{{ $payment['instructions'] }}</p>@endif<p>Transfer sesuai nominal pesanan. Kirim bukti sendiri melalui chat Telegram pribadi admin. Mengirim chat tidak mengubah status pembayaran.</p>@else<p class="auth-warning">Rincian pembayaran belum tersedia. Hubungi admin sebelum mentransfer.</p>@endif</x-ui.panel>
    @elseif(\App\Support\OrderDisplay::status($order) === 'expired')<p class="auth-warning">Batas pembayaran sudah lewat. Jangan mentransfer untuk pesanan ini. Jika dana terlanjur dikirim, hubungi admin untuk pemeriksaan manual.</p>
    @elseif(\App\Support\OrderDisplay::status($order) === 'rejected')<p class="auth-warning">Pesanan ditolak. Hubungi admin sebelum melakukan pembayaran lain.</p>
    @else<p class="auth-notice">Pembayaran tercatat lunas. Akses produk mengikuti lisensi yang diterbitkan setelah konfirmasi server.</p>@endif
    @if($order->status->value==='paid')
        @php($issued=$order->renewal_license_id?$order->renewalLicense:$order->issuedLicense)
        @if($issued && $issued->user_id===auth()->id())@can('download',$issued)<x-ui.panel title="Unduh produk"><p>File hanya tersedia selama lisensi Anda berhak mengakses.</p><a class="button button-lime" href="{{ route('licenses.download.request',$issued->id) }}">Unduh EX5 privat</a></x-ui.panel>@endcan @endif
    @endif
    <x-ui.panel title="Hubungi admin">
        <label for="order-text">Detail pesanan untuk dikirim sendiri</label><textarea id="order-text" class="auth-input" rows="7" readonly x-ref="orderText">{{ $clipboard }}</textarea>
        <div class="editor-status"><button class="button button-lime" type="button" @click="copyDetails()">Salin Detail Pesanan</button>@if($telegram)<a class="auth-secondary" href="{{ $telegram }}" target="_blank" rel="noopener noreferrer nofollow">Chat Admin di Telegram <x-ui.icon /></a>@else<p class="auth-hint">Kontak Telegram admin belum tersedia.</p>@endif</div>
        <p role="status" x-show="copied" x-cloak>Detail tersalin. Tempel sendiri saat menghubungi admin.</p><p role="status" x-show="copyError" x-cloak>Belum dapat menyalin otomatis. Teks sudah dipilih; salin secara manual.</p><p class="auth-hint">Membuka chat tidak mengirim teks atau bukti secara otomatis. Hanya konfirmasi admin di website yang dapat menetapkan pembayaran lunas.</p>
    </x-ui.panel>
    <x-ui.button :href="route('member.pesanan')">Kembali ke pesanan saya</x-ui.button>
</div>
