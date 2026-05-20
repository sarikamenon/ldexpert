@props([
    'name',
    'id' => null,
    'label',
    'subtext' => null,
    'tooltip' => null,
    'checked' => false,
    'disabled' => false,
    'value' => '1',
    'errorBag' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorBag ??= $name;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex items-start gap-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="checkbox"
        value="{{ $value }}"
        class="mt-0.5 w-4 h-4 rounded border-input text-primary focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
        @checked($checked)
        @disabled($disabled)
        {{ $attributes->except('class') }}
    >
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-1.5">
            <label for="{{ $fieldId }}" class="text-sm font-semibold text-foreground cursor-pointer">
                {{ $label }}
            </label>
            @if ($tooltip)
                <x-ui::tooltip-icon :content="$tooltip" />
            @endif
        </div>
        @if ($subtext)
            <p class="mt-0.5 text-xs text-foreground/60">{{ $subtext }}</p>
        @endif
        <x-input-error :messages="$errors->get($errorBag)" class="mt-1" />
    </div>
</div>
