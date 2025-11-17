@props(['activities' => []])

<x-ui::card class="p-6">
    <h3 class="text-lg font-semibold text-foreground mb-4">Recent Activity</h3>
    
    @if(count($activities) > 0)
        <div class="space-y-4">
            @foreach($activities as $activity)
                <div class="flex items-start space-x-3 pb-4 border-b border-border last:border-b-0 last:pb-0">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full bg-{{ $activity['color'] }}/10 flex items-center justify-center">
                            @if($activity['icon'] === 'school')
                                <svg class="w-4 h-4 text-{{ $activity['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            @elseif($activity['icon'] === 'user')
                                <svg class="w-4 h-4 text-{{ $activity['color'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-foreground">{{ $activity['description'] }}</p>
                        <p class="text-xs text-foreground/60 mt-1">
                            By {{ $activity['user'] }} • {{ $activity['created_at']->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-foreground/60 text-sm">No recent activity</p>
        </div>
    @endif
</x-ui::card>

