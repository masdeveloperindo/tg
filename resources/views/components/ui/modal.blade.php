@props(['id', 'title', 'description'])
<dialog wire:ignore.self {{ $attributes->class(['auth-modal', 'ui-request-dialog']) }} role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description" tabindex="-1"
    x-data="{ opener: document.activeElement, init() { this.$nextTick(() => { this.$el.showModal(); this.$el.querySelector('textarea, input, button')?.focus(); }); }, destroy() { this.$el.close(); this.opener?.focus(); } }"
    @keydown.tab="window.tgTrapDialog($event, $el)"
    @cancel.prevent="if (!$el.classList.contains('auth-is-busy')) $dispatch('modal-cancel')">
    <h2 id="{{ $id }}-title">{{ $title }}</h2>
    <p id="{{ $id }}-description" class="auth-muted">{{ $description }}</p>
    {{ $slot }}
</dialog>
