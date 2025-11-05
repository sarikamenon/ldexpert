<x-ui::card class="mb-6">
    <div class="p-5 border-b border-border">
        <h3 class="text-lg font-medium text-foreground">Menu</h3>
    </div>
    <div class="p-5 grid grid-cols-2 gap-3">
        {{ $slot }}
    </div>
</x-ui::card>
