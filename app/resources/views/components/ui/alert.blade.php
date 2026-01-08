@props(['variant' => 'info'])
@php
    $variants = [
        'info' => 'bg-background/subtle text-foreground border border-border',
        'success' => 'bg-success/10 text-success border border-success/20',
        'warning' => 'bg-warning/10 text-warning border border-warning/20',
        'danger' => 'bg-danger/10 text-danger border border-danger/20',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-base p-3 ' . ($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
</div>
