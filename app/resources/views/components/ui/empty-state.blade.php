@props([
    'title' => '',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<div class="py-12 text-center">
    @if(isset($icon) || $slot->isNotEmpty())
        <div class="mx-auto h-12 w-12 text-foreground/40 mb-4">
            @if(isset($icon))
                {{ $icon }}
            @else
                {{ $slot }}
            @endif
        </div>
    @endif

    <h3 class="text-sm font-medium text-foreground mb-1">
        {{ $title }}
    </h3>

    @if($description)
        <p class="text-sm text-foreground/60 mb-6">
            {{ $description }}
        </p>
    @endif

    @if($actionLabel && $actionHref)
        <x-ui::button variant="primary" href="{{ $actionHref }}">
            {{ $actionLabel }}
        </x-ui::button>
    @endif
</div>