@props([
    'name',
    'placeholder' => null,
    'searchable' => 'auto',
    'searchThreshold' => 10,
    'multiple' => false,
    'allowClear' => null,
    'tags' => false,
    'disabled' => false,
    'dropdownParent' => null,
    'width' => null,
    'inline' => false,
    'noResults' => null,
    'searchingMessage' => null,
])

@php
    // For inline mode (filters), don't use w-full so select2 can size naturally
    $baseClasses = $inline
        ? 'ld-select rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-primary focus:border-primary'
        : 'ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-primary focus:border-primary';

    // Default width: 'fit' for inline (auto-size based on content), '100%' for normal
    // 'fit' tells our select-box.js to use fit-content width
    $selectWidth = $width ?? ($inline ? 'fit' : '100%');
@endphp

<select name="{{ $name }}" {{ $attributes->merge(['class' => $baseClasses]) }}
    @if ($multiple) multiple @endif @disabled($disabled) data-select-box data-width="{{ $selectWidth }}" @if($inline) data-inline="true" @endif
    data-searchable="{{ $searchable === 'auto' ? 'auto' : ($searchable ? 'true' : 'false') }}"
    data-search-threshold="{{ $searchThreshold }}"
    @if ($allowClear !== null) data-allow-clear="{{ $allowClear ? 'true' : 'false' }}" @endif
    data-tags="{{ $tags ? 'true' : 'false' }}"
    @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif
    @if ($dropdownParent) data-dropdown-parent="{{ $dropdownParent }}" @endif
    @if ($noResults) data-no-results="{{ $noResults }}" @endif
    @if ($searchingMessage) data-searching-message="{{ $searchingMessage }}" @endif>
    {{ $slot }}
</select>
