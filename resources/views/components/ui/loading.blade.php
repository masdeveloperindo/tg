@props(['target', 'label' => 'Memproses…'])
<span {{ $attributes->class(['ui-loading']) }} wire:loading wire:target="{{ $target }}" role="status"><span class="ui-spinner" aria-hidden="true"></span> {{ $label }}</span>
