@props([
    'variant' => 'primary', // primary|secondary|ghost|success|danger|warning
    'size' => 'md', // sm|md|lg
    'type' => 'button',
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
        'warning' => 'bg-warning text-warning-foreground hover:bg-warning/90 active:bg-warning/80',
    ];
@endphp

@if($attributes->has('href'))
<a {{ $attributes->merge(['class' => $base . ' ' . $sizes[$size] . ' ' . ($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</a>
@else
<button type="{{ $type }}"
    {{ $attributes->merge(['class' => $base . ' ' . $sizes[$size] . ' ' . ($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</button>
@endif
