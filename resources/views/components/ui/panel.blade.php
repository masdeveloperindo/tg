@props(['title' => null, 'eyebrow' => null])

<section {{ $attributes->class(['ui-panel']) }}>
    @if($title || $eyebrow || isset($actions))
        <div class="ui-panel-header">
            <div>
                @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
                @if($title)<h2>{{ $title }}</h2>@endif
            </div>
            @isset($actions)<div class="ui-panel-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="ui-panel-body">{{ $slot }}</div>
</section>
