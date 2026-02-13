@props([
    'title',
    'subtitle' => null,
])

<div class="mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-foreground">{{ $title }}</h1>
            @if ($subtitle)
                <p class="text-sm text-foreground/60 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex items-center gap-3 flex-wrap">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>
