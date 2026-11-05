@props(['name', 'label', 'type' => 'text', 'hint' => null])

<div class="auth-field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($hint || $errors->has($name)) aria-describedby="{{ $hint ? $name.'-hint' : '' }} {{ $errors->has($name) ? $name.'-error' : '' }}" @endif
        {{ $attributes->class(['auth-input']) }}
    >
    @if ($hint)<p id="{{ $name }}-hint" class="auth-hint">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="auth-error">{{ $message }}</p>@enderror
</div>
