@props(['status' => []])

<x-ui::card class="p-6">
    <h3 class="text-lg font-semibold text-foreground mb-4">System Status</h3>
    
    <div class="space-y-3">
        @foreach($status as $service => $info)
            <div class="flex items-center justify-between p-3 rounded-lg bg-background/subtle">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 rounded-full bg-{{ $info['color'] }}"></div>
                    <div>
                        <p class="text-sm font-medium text-foreground capitalize">{{ str_replace('_', ' ', $service) }}</p>
                        <p class="text-xs text-foreground/60">{{ $info['message'] }}</p>
                    </div>
                </div>
                <x-ui::badge :variant="$info['color']">
                    {{ ucfirst($info['status']) }}
                </x-ui::badge>
            </div>
        @endforeach
    </div>
</x-ui::card>

