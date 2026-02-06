@props([
    'class' => 'h-4',
    'show' => true,
])

@if($show)
    <div {{ $attributes->merge(['class' => 'animate-pulse bg-foreground/10 rounded ' . $class]) }}></div>
@endif