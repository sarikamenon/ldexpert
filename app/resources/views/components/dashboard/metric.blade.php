<x-ui::card class="p-5" {{ $attributes }}>
    <div class="text-sm text-foreground/70">{{ $title }}</div>
    <div class="mt-2 text-3xl font-semibold text-foreground">{{ $value }}</div>
    @if (isset($badge))
        <div class="mt-3">{{ $badge }}</div>
    @endif
</x-ui::card>
