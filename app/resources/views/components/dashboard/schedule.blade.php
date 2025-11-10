<x-ui::card {{ $attributes }}>
    <div class="p-5 border-b border-border flex items-center justify-between">
        <h3 class="text-lg font-medium text-foreground">{{ $title }}</h3>
        <a href="#" class="text-sm text-accent hover:underline">View All</a>
    </div>
    <div class="p-5 space-y-4">
        {{ $slot }}
    </div>
</x-ui::card>
