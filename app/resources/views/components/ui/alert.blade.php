@props(['variant' => 'info'])
@php
    $variants = [
        'info' => 'bg-background subtle text-foreground border border-border',
        'success' => 'bg-green-50 text-green-700 border border-green-200',
        'warning' => 'bg-yellow-50 text-yellow-800 border border-yellow-200',
        'danger' => 'bg-red-50 text-red-700 border border-red-200',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-base p-3 ' . $variants[$variant]]) }}>
    {{ $slot }}
</div>
