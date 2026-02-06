@props([
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'loadingText' => 'Saving...',
    'type' => 'submit',
])

@php
    $base =
        'inline-flex items-center justify-center font-medium rounded-base transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'h-8 px-3 text-sm',
        'md' => 'h-9 px-4 text-sm',
        'lg' => 'h-10 px-5 text-base',
    ];
    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90 active:bg-primary/80',
        'secondary' =>
            'bg-background text-foreground border border-border hover:bg-background/subtle active:bg-background/muted',
        'ghost' => 'bg-transparent text-foreground hover:bg-background/muted active:bg-background/subtle',
        'success' => 'bg-success text-success-foreground hover:bg-success/90 active:bg-success/80',
        'danger' => 'bg-danger text-danger-foreground hover:bg-danger/90 active:bg-danger/80',
    ];
    $classes = $base . ' ' . $sizes[$size] . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

<button type="{{ $type }}" x-data="{ loading: {{ $loading ? 'true' : 'false' }} }" x-bind:disabled="loading"
    {{ $attributes->merge(['class' => $classes]) }}>
    <span x-show="!loading">
        {{ $slot }}
    </span>
    <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
            </circle>
            <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
        </svg>
        {{ $loadingText }}
    </span>
</button>
