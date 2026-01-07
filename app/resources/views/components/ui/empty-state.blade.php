@props([
    'title' => 'No data found',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-12']) }}>
    @if (isset($icon))
        <div class="mx-auto mb-4">
            {{ $icon }}
        </div>
    @endif

    <p class="text-sm font-medium text-foreground/70 mb-1">{{ $title }}</p>

    @if ($description)
        <p class="text-sm text-foreground/60 mb-4">
            {{ $description }}
        </p>
    @endif

    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}"
            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            {{ $actionLabel }}
        </a>
    @endif
</div>


