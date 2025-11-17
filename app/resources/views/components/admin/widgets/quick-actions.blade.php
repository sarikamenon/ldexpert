@props(['actions' => []])

<x-ui::card class="p-6">
    <h3 class="text-lg font-semibold text-foreground mb-4">Quick Actions</h3>
    
    <div class="grid grid-cols-1 gap-3">
        @foreach($actions as $action)
            <a href="{{ route($action['route']) }}" 
               class="flex items-start p-3 rounded-lg border border-border hover:border-{{ $action['color'] }} hover:bg-{{ $action['color'] }}/5 transition-colors">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-lg bg-{{ $action['color'] }}/10 flex items-center justify-center">
                        @if($action['icon'] === 'plus')
                            <svg class="w-5 h-5 text-{{ $action['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        @elseif($action['icon'] === 'list')
                            <svg class="w-5 h-5 text-{{ $action['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                        @elseif($action['icon'] === 'download')
                            <svg class="w-5 h-5 text-{{ $action['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        @endif
                    </div>
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-foreground">{{ $action['title'] }}</p>
                    <p class="text-xs text-foreground/60 mt-0.5">{{ $action['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-ui::card>

