@props([
    'selectedDate' => null,
    'timezone' => 'US/Central',
    'showDropdown' => false,
])

<div class="flex items-center justify-between mb-6" {{ $attributes }}>
    <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-foreground" id="selectedDateHeader">
            @if ($selectedDate)
                {{ $selectedDate->format('d F, Y') }} - {{ now()->format('h:i A') }} ({{ $timezone }})
            @else
                {{ now()->format('d F, Y') }} - {{ now()->format('h:i A') }} ({{ $timezone }})
            @endif
        </h2>
        @if ($showDropdown)
            <svg class="w-5 h-5 text-foreground/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        @endif
    </div>
</div>
