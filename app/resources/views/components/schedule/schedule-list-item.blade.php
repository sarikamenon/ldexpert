@props([
    'schedule' => [],
    'showActions' => true,
    'showNotes' => true,
    'compact' => false,
])

@php
    $scheduleDate = $schedule['schedule_date'] ?? null;
    $startTime = $schedule['start_time'] ?? null;
    $billingStatus = $schedule['billing_status'] ?? null;
    $isPast = $scheduleDate ? \Carbon\Carbon::parse($scheduleDate)->lt(now()->startOfDay()) : false;
    $hasEventStarted = ($scheduleDate && $startTime)
        ? now()->gte(\Carbon\Carbon::parse($scheduleDate . ' ' . $startTime))
        : $isPast;
    $isBilled = $billingStatus === 'billed';
    $isPendingBilling = $billingStatus === 'pending';
@endphp

@if ($compact)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-background px-3 py-2.5 transition-colors hover:bg-background/subtle']) }}>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 flex-1 gap-3">
                <div class="shrink-0 text-xs font-medium leading-5 text-foreground sm:w-24">
                    <span>{{ $schedule['start_time'] ?? '' }}</span>
                    @if (isset($schedule['end_time']))
                        <span class="text-foreground/50">-</span>
                        <span>{{ $schedule['end_time'] }}</span>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    @if (isset($schedule['student']) || isset($schedule['therapist']))
                        @if (isset($schedule['student']))
                            @if (isset($schedule['student_url']))
                                <a href="{{ $schedule['student_url'] }}"
                                    class="block truncate text-sm font-semibold text-accent hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    {{ $schedule['student'] }}
                                </a>
                            @else
                                <span class="block truncate text-sm font-semibold text-accent">
                                    {{ $schedule['student'] }}
                                </span>
                            @endif
                        @else
                            <span class="block truncate text-sm font-semibold text-foreground">
                                {{ $schedule['therapist'] ?? '' }}
                            </span>
                        @endif
                    @endif

                    <div class="mt-0.5 truncate text-xs text-foreground/60">
                        {{ $schedule['service'] ?? 'Service not set' }}
                        @if (!empty($schedule['school']))
                            <span class="text-foreground/40">·</span>
                            {{ $schedule['school'] }}
                        @elseif (!empty($schedule['location_details']))
                            <span class="text-foreground/40">·</span>
                            {{ Str::limit($schedule['location_details'], 48) }}
                        @endif
                    </div>
                </div>
            </div>

            @if ($showActions)
                <div class="flex shrink-0 items-center gap-1.5 sm:ml-3">
                    <button type="button"
                        class="schedule-details-view-btn rounded-lg border border-border p-1.5 transition-colors hover:bg-background/subtle active:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Details"
                        aria-label="View schedule details for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-foreground" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>

                    @if (!$isBilled)
                        @if (isset($schedule['edit_url']))
                            <a href="{{ $schedule['edit_url'] }}"
                                class="rounded-lg border border-border p-1.5 transition-colors hover:bg-background/subtle active:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                title="Edit Schedule"
                                aria-label="Edit schedule for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                                <svg class="h-4 w-4 text-foreground" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                        @else
                            <button type="button"
                                class="schedule-edit-btn rounded-lg border border-border p-1.5 transition-colors hover:bg-background/subtle active:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                                data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Edit Schedule"
                                aria-label="Edit schedule for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                                <svg class="h-4 w-4 text-foreground" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                        @endif

                        <button type="button"
                            class="schedule-delete-btn rounded-lg border border-danger/30 p-1.5 text-danger transition-colors hover:border-danger/50 hover:bg-danger/10 active:bg-danger/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Delete Schedule"
                            aria-label="Delete schedule for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    @endif

                    @if ($hasEventStarted && $isPendingBilling)
                        @if (isset($schedule['bill_url']))
                            <a href="{{ $schedule['bill_url'] }}"
                                class="rounded-lg bg-primary p-1.5 text-white transition-colors hover:bg-primary/90 active:bg-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                title="Bill Your Session"
                                aria-label="Bill session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </a>
                        @else
                            <button type="button"
                                class="schedule-bill-btn rounded-lg bg-primary p-1.5 text-white transition-colors hover:bg-primary/90 active:bg-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                                data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Bill Your Session"
                                aria-label="Bill session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        @endif
                    @elseif ($isPast && $isBilled)
                        <button type="button"
                            class="schedule-view-session-btn rounded-lg bg-primary/10 p-1.5 text-primary transition-colors hover:bg-primary/20 active:bg-primary/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Session"
                            aria-label="View session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
@else
<div {{ $attributes->merge(['class' => 'border border-border rounded-lg p-4 mb-4']) }}>
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
                                class="font-semibold text-accent text-sm hover:underline">
                                {{ $schedule['student'] }}
                            </a>
                        @else
                            <span class="font-semibold text-accent text-sm">
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
                    class="schedule-details-view-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Details"
                    aria-label="View schedule details for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
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
                            class="p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            title="Edit Schedule"
                            aria-label="Edit schedule for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                    @else
                        <button type="button"
                            class="schedule-edit-btn p-2 border border-border rounded-lg hover:bg-background/subtle transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Edit Schedule"
                            aria-label="Edit schedule for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="w-5 h-5 text-foreground" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                    @endif

                    {{-- Delete Schedule Button (hidden for billed schedules) --}}
                    <button type="button"
                        class="schedule-delete-btn p-2 border border-danger/30 rounded-lg hover:bg-danger/10 hover:border-danger/50 transition-colors text-danger"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Delete Schedule">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                @endif

                {{-- Billing / View Session Buttons (after event start time) --}}
                @if ($hasEventStarted && $isPendingBilling)
                    @if (isset($schedule['bill_url']))
                        <a href="{{ $schedule['bill_url'] }}"
                            class="p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            title="Bill Your Session"
                            aria-label="Bill session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </a>
                    @else
                        <button type="button"
                            class="schedule-bill-btn p-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            data-schedule-id="{{ $schedule['id'] ?? '' }}" title="Bill Your Session"
                            aria-label="Bill session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    @endif
                @elseif ($isPast && $isBilled)
                    <button type="button"
                        class="schedule-view-session-btn p-2 bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        data-schedule-id="{{ $schedule['id'] ?? '' }}" title="View Session"
                        aria-label="View session for {{ $schedule['student'] ?? $schedule['therapist'] ?? 'schedule' }}">
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
@endif
