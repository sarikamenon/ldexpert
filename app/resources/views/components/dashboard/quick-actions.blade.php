<x-ui::card>
    <div class="p-5 border-b border-border">
        <h3 class="text-lg font-medium text-foreground">Quick Actions</h3>
    </div>
    <div class="p-5 space-y-3">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="p-5 border-t border-border space-y-3">
            {{ $footer }}
        </div>
    @endisset
</x-ui::card>
