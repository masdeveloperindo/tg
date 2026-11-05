@props(['href' => null, 'type' => 'button', 'variant' => 'secondary'])

@php
    $style = in_array($variant, ['lime', 'secondary', 'quiet'], true) ? $variant : 'secondary';
    $buttonType = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
@endphp

@if($href !== null)
    <a href="{{ $href }}" {{ $attributes->class(['button', 'button-'.$style]) }}>{{ $slot }}</a>
@else
    <button type="{{ $buttonType }}" {{ $attributes->class(['button', 'button-'.$style]) }}>{{ $slot }}</button>
@endif
