@props(['href' => null])

@if ($href)
    <a href="{{ $href }}" role="menuitem"
        {{ $attributes->merge(['class' => 'block px-3 py-2 text-sm text-foreground hover:bg-gray-50 whitespace-nowrap']) }}>
        {{ $slot }}
    </a>
@else
    <div role="menuitem"
        {{ $attributes->merge(['class' => 'block px-3 py-2 text-sm text-foreground hover:bg-gray-50 whitespace-nowrap']) }}>
        {{ $slot }}
    </div>
@endif
