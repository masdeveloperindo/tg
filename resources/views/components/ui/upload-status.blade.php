@props(['field' => null])
<div {{ $attributes }} x-data="{ state: 'idle', progress: 0, field: @js($field) }"
    @upload-validated.window="if ($event.detail.fields?.includes(field)) { state = 'idle'; progress = 0 }"
    x-on:livewire-upload-start="state = 'uploading'; progress = 0"
    x-on:livewire-upload-progress="if (state === 'uploading') progress = Math.max(0, Math.min(100, Number($event.detail.progress) || 0))"
    x-on:livewire-upload-finish="state = 'processing'"
    x-on:livewire-upload-error="state = 'error'"
    x-on:livewire-upload-cancel="state = 'cancelled'">
    {{ $slot }}
    <div role="status" aria-live="polite">
        <template x-if="state === 'uploading'"><label>Mengunggah <progress max="100" :value="progress" :aria-valuenow="progress"></progress> <span x-text="progress + '%'"></span></label></template>
        <p x-show="state === 'processing'" x-cloak>Transfer selesai. Menunggu validasi server; file belum dinyatakan tersimpan.</p>
        <p x-show="state === 'error'" x-cloak>Unggahan gagal. Periksa koneksi dan pilih kembali file.</p>
        <p x-show="state === 'cancelled'" x-cloak>Unggahan dibatalkan.</p>
    </div>
</div>
