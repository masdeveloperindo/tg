@props(['tone' => 'neutral'])

@php
    $style = in_array($tone, ['neutral', 'success', 'warning', 'danger'], true) ? $tone : 'neutral';
    $symbol = match ($style) { 'success' => '✓', 'warning' => '!', 'danger' => '×', default => '•' };
@endphp

<span {{ $attributes->class(['ui-badge', 'ui-badge-'.$style]) }}><span aria-hidden="true">@if ($style === 'success')<x-ui.icon name="check" />@elseif ($style === 'danger')<x-ui.icon name="close" />@else{{ $symbol }}@endif</span> {{ $slot }}</span>
