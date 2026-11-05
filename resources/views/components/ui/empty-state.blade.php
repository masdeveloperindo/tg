@props(['title', 'icon' => '↗'])

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon" aria-hidden="true">@if ($icon === '↗')<x-ui.icon />@else{{ $icon }}@endif</span>
    <div class="empty-state-body">
        <h3>{{ $title }}</h3>
        <div>{{ $slot }}</div>
        @isset($actions)<div class="empty-state-actions">{{ $actions }}</div>@endisset
    </div>
</div>
