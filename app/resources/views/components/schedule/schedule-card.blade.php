@props([
    'schedule' => [],
    'showActions' => true,
    'showNotes' => true,
])

<div class="border border-border rounded-lg p-4 mb-4" {{ $attributes }}>
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-4 mb-2">
                <span class="text-sm font-medium text-foreground">{{ $schedule['start_time'] ?? '' }}</span>
                @if (isset($schedule['end_time']))
                    <span class="text-sm text-foreground/70">-</span>
                    <span class="text-sm font-medium text-foreground">{{ $schedule['end_time'] }}</span>
                @endif
            </div>

            @if (isset($schedule['school']))
                <div class="mb-2">
                    @if (isset($schedule['school_url']))
                        <a href="{{ $schedule['school_url'] }}"
                            class="text-primary hover:underline">{{ $schedule['school'] }}</a>
                    @else
                        <span class="text-primary">{{ $schedule['school'] }}</span>
                    @endif
                </div>
            @endif

            @if (isset($schedule['student']) || isset($schedule['therapist']))
                <div class="mb-2">
                    <span
                        class="font-semibold text-foreground">{{ $schedule['student'] ?? ($schedule['therapist'] ?? '') }}</span>
                </div>
            @endif

            @if (isset($schedule['session_type']))
                <div class="flex items-center gap-2 text-sm text-foreground/70">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $schedule['session_type'] }}</span>
                </div>
            @endif

            @if ($showNotes && isset($schedule['notes']))
                <div class="mt-3">
                    <label class="block text-sm font-medium text-foreground/70 mb-1">Notes:</label>
                    <textarea class="w-full border border-border rounded-lg px-3 py-2 text-sm" rows="3" readonly>{{ $schedule['notes'] }}</textarea>
                </div>
            @endif
        </div>

        @if ($showActions)
            <div class="flex flex-col gap-2 ml-4">
                @if (isset($schedule['edit_url']))
                    <a href="{{ $schedule['edit_url'] }}"
                        class="px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle text-center">
                        EDIT SCHEDULE
                    </a>
                @else
                    <button type="button"
                        class="schedule-edit-btn px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}">
                        EDIT SCHEDULE
                    </button>
                @endif

                @if (isset($schedule['bill_url']))
                    <a href="{{ $schedule['bill_url'] }}"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium flex items-center justify-center gap-2">
                        BILL YOUR SESSION
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>
                @else
                    <button type="button"
                        class="schedule-bill-btn px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium flex items-center justify-center gap-2"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}">
                        BILL YOUR SESSION
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
