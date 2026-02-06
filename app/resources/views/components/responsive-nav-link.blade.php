@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-primary text-start text-base font-medium text-primary bg-background/subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-ring transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-foreground/70 hover:text-foreground hover:bg-background/subtle hover:border-border focus:outline-none focus-visible:ring-2 focus-visible:ring-ring transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
