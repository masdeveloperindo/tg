<div><x-site-header :compact="true" /><main id="konten" class="shell section" tabindex="-1"><p class="eyebrow">CHECKOUT / PEMBAYARAN MANUAL</p><h1>{{ $renewalId ? 'Perpanjang akses' : 'Pesan akses' }} {{ $product->name }}</h1>
    @include('livewire.auth.partials.feedback')
    @if($unavailable)<p class="auth-warning">{{ $unavailable }}</p>@endif
    @if($quote)
        <section class="auth-card"><h2>{{ \App\Support\OrderDisplay::money($quote->amount_idr) }}</h2><p>Akses sampai <strong>31 Desember {{ $quote->coverage_year }}</strong> (23:59:59 WIB).</p><p class="auth-hint">Harga ini berlaku sampai {{ $quote->expires_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB. Setelah itu, muat harga terbaru sebelum memesan.</p><p>Lisensi berlaku untuk satu produk dan satu akun MT5. IB tidak wajib. Transfer dan bukti pembayaran diperiksa manual oleh admin.</p></section>
        <form wire:submit="submit" class="auth-card auth-form">
            <x-ui.editor-field model="mt5_account" label="Nomor akun MT5" inputmode="numeric" maxlength="24" :readonly="$renewalId !== null" />
            <x-ui.editor-field model="mt5_server" label="Nama server MT5" maxlength="100" :readonly="$renewalId !== null" hint="Gunakan nama server persis seperti di terminal MT5. Diisi sekali untuk pesanan ini." />
            @if($renewalId)<p>Jenis akun: {{ $account_type === 'real' ? 'Real' : 'Demo' }}. Perpanjangan memakai akun lisensi yang sama.</p>@else<x-ui.editor-field model="account_type" label="Jenis akun MT5" :options="['real'=>'Real','demo'=>'Demo']" />@endif
            <fieldset><legend>Metode pembayaran</legend>@foreach($quote->payment_details as $method)<label class="auth-checkbox" for="method-{{ $method['id'] }}"><input id="method-{{ $method['id'] }}" type="radio" wire:model="payment_method_id" value="{{ $method['id'] }}"><span>{{ $method['label'] }} · {{ $method['account_number'] }} · {{ $method['account_holder'] }}</span></label>@endforeach</fieldset>
            <p class="auth-hint">Buat pesanan terlebih dahulu. Rincian transfer dan batas bayar tampil pada halaman pesanan. Belum ada pembayaran otomatis atau lisensi yang diterbitkan.</p>
            @if(now()->gte($quote->expires_at))<p class="auth-warning">Kuotasi sudah berakhir. Muat harga terbaru sebelum membuat pesanan.</p>@endif
            <button type="submit" @disabled(now()->gte($quote->expires_at)) class="button button-lime" wire:loading.attr="disabled" wire:target="submit,refreshQuote">Buat Pesanan</button><x-ui.loading target="submit" label="Membuat pesanan…" />
        </form>
        <x-ui.button wire:click="refreshQuote" wire:loading.attr="disabled" wire:target="submit,refreshQuote">Muat harga terbaru</x-ui.button>
    @endif
    <p><a class="auth-secondary" href="{{ route('member.pesanan') }}">Lihat pesanan saya</a></p><x-risk-note />
</main><x-site-footer /></div>
