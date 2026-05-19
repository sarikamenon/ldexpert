@props([
    'name',
    'class' => 'w-4 h-4',
])

@php
    $paths = [
        'download' => [
            '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>',
            '<polyline points="7 10 12 15 17 10"></polyline>',
            '<line x1="12" y1="15" x2="12" y2="3"></line>',
        ],
        'plus' => [
            '<line x1="12" y1="5" x2="12" y2="19"></line>',
            '<line x1="5" y1="12" x2="19" y2="12"></line>',
        ],
    ];
    $shapes = $paths[$name] ?? null;
@endphp

@if ($shapes)
    <svg xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        {!! implode('', $shapes) !!}
    </svg>
@endif
