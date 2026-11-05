@props(['model', 'label', 'type' => 'text', 'options' => null, 'hint' => null])
@php($fieldId = 'editor-'.str_replace('.', '-', $model))
<div class="auth-field">
    <label for="{{ $fieldId }}">{{ $label }}</label>
    @if($options !== null)
        <select id="{{ $fieldId }}" class="auth-input" wire:model="{{ $model }}"><option value="">Pilih…</option>@foreach($options as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select>
    @elseif($type === 'textarea')
        <textarea id="{{ $fieldId }}" class="auth-input" wire:model="{{ $model }}" rows="5" {{ $attributes }}></textarea>
    @else
        <input id="{{ $fieldId }}" class="auth-input" wire:model="{{ $model }}" type="{{ $type }}" {{ $attributes }}>
    @endif
    @if($hint)<p class="auth-hint">{{ $hint }}</p>@endif
    @error(\Illuminate\Support\Str::afterLast($model, '.'))<p class="auth-error">{{ $message }}</p>@enderror
</div>
