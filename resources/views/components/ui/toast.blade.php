@props(['message'])
<div {{ $attributes->class(['auth-notice', 'ui-toast']) }} role="status" x-data="{ visible: true }" x-show="visible">
    <span>{{ $message }}</span><button type="button" class="auth-secondary" @click="visible = false" aria-label="Tutup pemberitahuan">Tutup</button>
</div>
