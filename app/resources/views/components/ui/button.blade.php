@props([
    'variant' => 'primary', // primary|secondary|ghost|success|danger
    'size' => 'md', // sm|md|lg
    'type' => 'button',
])

@php
    $base =
        'inline-flex items-center justify-center font-medium rounded-base transition-colors focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'h-8 px-3 text-sm',
        'md' => 'h-9 px-4 text-sm',
        'lg' => 'h-10 px-5 text-base',
    ];
    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90',
        'secondary' => 'bg-background text-foreground border border-border hover:bg-background/subtle',
        'ghost' => 'bg-transparent text-foreground hover:bg-background/muted',
        'success' => 'bg-success text-success-foreground hover:bg-success/90',
        'danger' => 'bg-danger text-danger-foreground hover:bg-danger/90',
    ];
@endphp

<button type="{{ $type }}"
    {{ $attributes->merge(['class' => $base . ' ' . $sizes[$size] . ' ' . $variants[$variant]]) }}>
    {{ $slot }}
</button>
