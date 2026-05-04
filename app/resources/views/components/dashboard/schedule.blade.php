@props(['title', 'viewAllUrl' => '#', 'countLabel' => null, 'viewAllLabel' => 'View All'])

<x-ui::card {{ $attributes }}>
    <div class="p-5 border-b border-border flex items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-medium text-foreground">{{ $title }}</h3>
            @if ($countLabel)
                <p class="mt-1 text-xs text-foreground/60">{{ $countLabel }}</p>
            @endif
        </div>
        <a href="{{ $viewAllUrl }}"
            class="shrink-0 text-sm text-accent hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            {{ $viewAllLabel }}
        </a>
    </div>
    <div class="p-5 space-y-2">
        {{ $slot }}
    </div>
</x-ui::card>
