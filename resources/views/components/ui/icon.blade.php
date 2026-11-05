@props(['name' => 'arrow-up-right'])
<svg {{ $attributes->class(['ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('menu')<path d="M4 6h16M4 12h16M4 18h16" />@break
        @case('close')<path d="m6 6 12 12M18 6 6 18" />@break
        @case('arrow-right')<path d="M4 12h16m-6-6 6 6-6 6" />@break
        @case('check')<path d="m5 12 4 4L19 6" />@break
        @default<path d="M7 17 17 7M7 7h10v10" />
    @endswitch
</svg>
