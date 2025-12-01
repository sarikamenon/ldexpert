@props([
    'schedule' => [],
    'showActions' => true,
    'showNotes' => true,
])

<x-schedule.schedule-list-item :schedule="$schedule" :show-actions="$showActions" :show-notes="$showNotes" {{ $attributes }} />
