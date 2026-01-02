@props(['variant' => 'primary'])
@php
    $variants = [
        'primary' => 'bg-primary/10 text-primary border border-primary/20',
        'secondary' => 'bg-gray-100 text-gray-700 border border-gray-200',
        'muted' => 'bg-background/subtle text-foreground border border-border',
        'success' => 'bg-success/10 text-success border border-success/20',
        'warning' => 'bg-warning/10 text-warning border border-warning/20',
        'danger' => 'bg-danger/10 text-danger border border-danger/20',
        'info' => 'bg-blue-100 text-blue-700 border border-blue-200',
    ];
@endphp
<span
    {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium ' . ($variants[$variant] ?? $variants['secondary'])]) }}>
    {{ $slot }}
</span>
