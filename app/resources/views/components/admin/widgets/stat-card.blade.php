@props(['title', 'value', 'icon' => null, 'color' => 'primary', 'trend' => null])

<x-ui::card class="p-6">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <p class="text-sm font-medium text-foreground/70 mb-1">{{ $title }}</p>
            <p class="text-2xl font-semibold text-foreground">{{ $value }}</p>
            
            @if($trend)
                <p class="text-xs mt-2 {{ $trend['type'] === 'up' ? 'text-success' : 'text-danger' }}">
                    <span>{{ $trend['value'] }}</span>
                    <span class="text-foreground/60">{{ $trend['label'] }}</span>
                </p>
            @endif
        </div>
        
        @if($icon)
            <div class="ml-4">
                <div class="w-12 h-12 rounded-lg bg-{{ $color }}/10 flex items-center justify-center">
                    @if($icon === 'school')
                        <svg class="w-6 h-6 text-{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    @elseif($icon === 'user')
                        <svg class="w-6 h-6 text-{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    @elseif($icon === 'users')
                        <svg class="w-6 h-6 text-{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    @elseif($icon === 'check')
                        <svg class="w-6 h-6 text-{{ $color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-ui::card>

