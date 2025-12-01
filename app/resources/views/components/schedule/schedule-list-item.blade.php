@props([
    'schedule' => [],
    'showActions' => true,
    'showNotes' => true,
])

@php
    $scheduleDate = $schedule['schedule_date'] ?? null;
    $billingStatus = $schedule['billing_status'] ?? null;
    $isPast = $scheduleDate ? \Carbon\Carbon::parse($scheduleDate)->lt(now()->startOfDay()) : false;
    $isBilled = $billingStatus === 'billed';
    $isPendingBilling = $billingStatus === 'pending';
@endphp

<div class="border border-border rounded-lg p-4 mb-4" {{ $attributes }}>
    <div class="flex items-start justify-between">
        <div class="flex-1">
            {{-- Compact View (Always Visible) --}}
            <div class="flex items-center gap-4 mb-2">
                <span class="text-sm font-medium text-foreground">{{ $schedule['start_time'] ?? '' }}</span>
                @if (isset($schedule['end_time']))
                    <span class="text-sm text-foreground/70">-</span>
                    <span class="text-sm font-medium text-foreground">{{ $schedule['end_time'] }}</span>
                @endif
            </div>

            @if (isset($schedule['student']) || isset($schedule['therapist']))
                <div class="mb-1">
                    @if (isset($schedule['student']))
                        @if (isset($schedule['student_url']))
                            <a href="{{ $schedule['student_url'] }}"
                                class="font-semibold text-foreground text-sm hover:underline">
                                {{ $schedule['student'] }}
                            </a>
                        @else
                            <span class="font-semibold text-foreground text-sm">
                                {{ $schedule['student'] }}
                            </span>
                        @endif
                    @else
                        <span class="font-semibold text-foreground text-sm">
                            {{ $schedule['therapist'] ?? '' }}
                        </span>
                    @endif
                </div>
            @endif


            @if (isset($schedule['service']))
                <div class="text-sm text-foreground/70 mb-2">{{ $schedule['service'] }}</div>
            @endif
        </div>

        @if ($showActions)
            <div class="flex items-center gap-2 ml-4">
                {{-- Details View Button --}}
                <button type="button"
                    class="schedule-details-view-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors"
                    data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Details">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-foreground" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>

                {{-- Edit Button (hidden for billed schedules) --}}
                @if (!$isBilled)
                    @if (isset($schedule['edit_url']))
                        <a href="{{ $schedule['edit_url'] }}"
                            class="p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors"
                            title="Edit Schedule">
                            <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                    @else
                        <button type="button"
                            class="schedule-edit-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Edit Schedule">
                            <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    @endif
                @endif

                {{-- Billing / View Session Buttons (only for past schedules) --}}
                @if ($isPast && $isPendingBilling)
                    @if (isset($schedule['bill_url']))
                        <a href="{{ $schedule['bill_url'] }}"
                            class="p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors"
                            title="Bill Your Session">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </a>
                    @else
                        <button type="button"
                            class="schedule-bill-btn p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Bill Your Session">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    @endif
                @elseif ($isPast && $isBilled)
                    <button type="button"
                        class="schedule-view-session-btn p-2 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Session">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
