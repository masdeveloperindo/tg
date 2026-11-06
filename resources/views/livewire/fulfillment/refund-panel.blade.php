<div><x-ui.panel title="Refund manual">
<p>Refund uang dan pencabutan lisensi dicatat terpisah. Pesanan lunas tetap tersimpan sebagai riwayat pembayaran.</p>
@include('livewire.auth.partials.feedback')
@can('confirmPayment',$order)@if($order->status->value==='paid')<x-ui.button wire:click="open('initiate')">Catat rencana refund</x-ui.button>@endif @else<x-ui.button :href="route('admin.reauth')">Verifikasi ulang admin</x-ui.button>@endcan
@forelse($refunds as $refund)<div class="auth-client-row"><div><p>Refund #{{ $refund->id }} · {{ \App\Support\OrderDisplay::money($refund->amount_idr) }} · {{ $refund->status->value }}</p><p>{{ $refund->reason }}</p></div>@if($refund->status->value==='initiated')@can('confirmPayment',$order)<div><x-ui.button wire:click="open('completed',{{ $refund->id }})">Catat transfer selesai</x-ui.button><x-ui.button wire:click="open('rejected',{{ $refund->id }})">Tolak refund</x-ui.button></div>@endcan @endif</div>@empty<p>Belum ada refund.</p>@endforelse
</x-ui.panel>
@if($action)<x-ui.modal id="refund-confirm" title="Konfirmasi catatan refund" description="Aplikasi tidak mengirim uang. Catat selesai hanya setelah transfer manual berhasil diperiksa." wire:loading.class="auth-is-busy" wire:target="execute,cancel" x-on:modal-cancel="$wire.cancel()">@include('livewire.auth.partials.feedback')
<form wire:submit="execute" class="auth-form">
@if($action==='initiate')<x-ui.editor-field model="amount" label="Nominal refund (IDR)" inputmode="decimal" /><x-ui.editor-field model="reason" label="Alasan refund" type="textarea" maxlength="500" />
@elseif($action==='completed')<label class="auth-checkbox"><input type="checkbox" wire:model="transferred" required><span>Transfer refund sudah berhasil dan saya sudah memeriksanya.</span></label><x-ui.editor-field model="reference" label="Referensi transfer manual" maxlength="120" />
@else<p>Rencana refund akan ditolak. Tidak ada uang yang dianggap telah dikembalikan.</p>@endif
<div class="auth-modal-actions"><button type="button" class="auth-secondary" wire:click="cancel" wire:loading.attr="disabled">Batal</button><button type="submit" class="button button-lime" wire:loading.attr="disabled">Konfirmasi catatan</button><x-ui.loading target="execute" label="Menyimpan refund…" /></div></form></x-ui.modal>@endif</div>
