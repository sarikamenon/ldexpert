@props([
    'schedules' => [],
    'selectedDate' => null,
    'emptyMessage' => null,
    'showAddButton' => true,
    'addButtonUrl' => '#',
    'addButtonText' => 'ADD NEW SCHEDULE',
])

<div {{ $attributes->merge(['id' => 'scheduleList']) }}>
    @if (count($schedules) > 0)
        @foreach ($schedules as $schedule)
            <x-schedule.schedule-list-item :schedule="$schedule" />
        @endforeach
    @else
        <div class="text-center py-12">
            <p class="text-foreground/70 mb-4">
                {{ $emptyMessage ?? 'You don\'t have any schedule for ' . ($selectedDate ? $selectedDate->format('F jS') : 'this date') . '.' }}
            </p>
            @if ($showAddButton)
                <a href="{{ $addButtonUrl }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $addButtonText }}
                </a>
            @endif
        </div>
    @endif
</div>
