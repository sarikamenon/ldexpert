@props([
    'items' => [],
])

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    @foreach ($items as $item)
        <x-ui::card class="p-4 space-y-1">
            <p class="text-sm text-foreground/70">{{ $item['label'] ?? '' }}</p>
            <p class="text-2xl font-semibold {{ $item['valueClass'] ?? '' }}">
                {{ $item['value'] ?? '' }}
            </p>
            @if (!empty($item['subtext']))
                <p class="text-xs text-foreground/60">{{ $item['subtext'] }}</p>
            @endif
        </x-ui::card>
    @endforeach
</div>
