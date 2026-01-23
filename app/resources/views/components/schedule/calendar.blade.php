@props([
    'selectedDate' => null,
    'showTodayButton' => true,
    'onDateSelect' => null, // JavaScript function name to call on date select
])

@php
    $selectedDateFormatted = $selectedDate ? $selectedDate->format('Y-m-d') : now()->format('Y-m-d');
    // Extract timezone label from attributes if present
    $timezoneLabel = $attributes->get('data-therapist-timezone-label', 'Central Time (CT)');
@endphp

<x-ui::card class="p-4">
    @if ($showTodayButton)
        <button type="button"
            class="calendar-today-btn w-full mb-4 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
            TODAY'S VIEW
        </button>
    @endif

    <div id="calendar" data-selected-date="{{ $selectedDateFormatted }}"
        data-therapist-timezone-label="{{ $timezoneLabel }}"
        @if ($onDateSelect) data-on-date-select="{{ $onDateSelect }}" @endif
        {{ $attributes->except(['data-therapist-timezone-label', 'data-on-date-select'])->merge(['class' => 'calendar-widget']) }}>
        {{-- Calendar will be rendered by JavaScript --}}
    </div>
</x-ui::card>
