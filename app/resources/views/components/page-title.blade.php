@props([
    'title' => null,
    'description' => null,
])

<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-foreground">
            {{ $title ?? $slot }}
        </h1>
        @if ($description)
            <p class="mt-1 text-sm text-foreground/70">
                {{ $description }}
            </p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">
            {{ $actions }}
        </div>
    @endisset
</div>
