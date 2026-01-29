@props([
    'name',
    'placeholder' => null,
    'searchable' => 'auto',
    'searchThreshold' => 10,
    'multiple' => false,
    'allowClear' => false,
    'tags' => false,
    'disabled' => false,
    'dropdownParent' => null,
    'width' => '100%',
    'noResults' => null,
    'searchingMessage' => null,
])

@php
    $baseClasses =
        'ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-primary focus:border-primary';
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => $baseClasses]) }}
    @if ($multiple) multiple @endif @disabled($disabled) data-select-box data-width="{{ $width }}"
    data-searchable="{{ $searchable === 'auto' ? 'auto' : ($searchable ? 'true' : 'false') }}"
    data-search-threshold="{{ $searchThreshold }}"
    data-allow-clear="{{ $allowClear ? 'true' : 'false' }}"
    data-tags="{{ $tags ? 'true' : 'false' }}"
    @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif
    @if ($dropdownParent) data-dropdown-parent="{{ $dropdownParent }}" @endif
    @if ($noResults) data-no-results="{{ $noResults }}" @endif
    @if ($searchingMessage) data-searching-message="{{ $searchingMessage }}" @endif>
    {{ $slot }}
</select>
