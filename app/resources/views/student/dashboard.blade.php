<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 lg:px-8 space-y-6">
            <!-- Welcome Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">Welcome, {{ auth()->user()->name }}!</h1>
                <p class="text-foreground/70 mt-1">Here's your schedule for today</p>
            </div>

            @if ($hasSchedules)
                <!-- Next Upcoming Schedule Message -->
                @if ($nextSchedule)
                    @php
                        $scheduleDateTime = $nextSchedule['schedule_datetime'];
                        $timeUntil = now()->diffForHumans($scheduleDateTime, true);
                    @endphp
                    <div class="bg-primary/10 border border-primary/20 rounded-lg p-6 mb-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-foreground mb-2">Upcoming Session</h3>
                                <p class="text-foreground/90 mb-3">
                                    You have a scheduled session with
                                    <strong>{{ $nextSchedule['therapist_name'] }}</strong>
                                    for <strong>{{ $nextSchedule['service_name'] }}</strong>
                                    at <strong>{{ $nextSchedule['start_time'] }}</strong>.
                                </p>
                                <p class="text-sm text-foreground/70">
                                    @if ($scheduleDateTime->isToday())
                                        Your session is in {{ $timeUntil }}. Please come back at that time.
                                    @else
                                        Your session is scheduled for {{ $nextSchedule['schedule_date'] }} at
                                        {{ $nextSchedule['start_time'] }}. Please come back at that time.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Today's Schedule List -->
                <div class="bg-background border border-border rounded-lg">
                    <div class="p-5 border-b border-border">
                        <h2 class="text-lg font-semibold text-foreground">Today's Schedule</h2>
                    </div>
                    <div class="p-5 space-y-4">
                        @foreach ($todaySchedules as $schedule)
                            <div
                                class="border border-border rounded-lg p-4 hover:bg-background/subtle transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="text-lg font-semibold text-foreground">
                                                {{ $schedule['start_time'] }}
                                            </span>
                                            <span class="text-foreground/60">-</span>
                                            <span class="text-lg font-semibold text-foreground">
                                                {{ $schedule['end_time'] }}
                                            </span>
                                            @if ($schedule['is_upcoming'])
                                                <span
                                                    class="px-2 py-1 text-xs font-medium bg-primary/10 text-primary rounded">
                                                    Upcoming
                                                </span>
                                            @endif
                                        </div>

                                        <div class="space-y-1">
                                            <div class="text-sm">
                                                <span class="text-foreground/70">Therapist:</span>
                                                <span
                                                    class="font-medium text-foreground ml-1">{{ $schedule['therapist_name'] }}</span>
                                            </div>
                                            <div class="text-sm">
                                                <span class="text-foreground/70">Service:</span>
                                                <span
                                                    class="font-medium text-foreground ml-1">{{ $schedule['service_name'] }}</span>
                                            </div>
                                            @if ($schedule['school'])
                                                <div class="text-sm">
                                                    <span class="text-foreground/70">School:</span>
                                                    <span
                                                        class="font-medium text-foreground ml-1">{{ $schedule['school'] }}</span>
                                                </div>
                                            @endif
                                            @if ($schedule['location_details'])
                                                <div class="text-sm">
                                                    <span class="text-foreground/70">Location:</span>
                                                    <span
                                                        class="font-medium text-foreground ml-1">{{ $schedule['location_details'] }}</span>
                                                </div>
                                            @endif
                                        </div>

                                        @if ($schedule['notes'])
                                            <div class="mt-3 pt-3 border-t border-border">
                                                <p class="text-sm text-foreground/70">{{ $schedule['notes'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <!-- No Schedules Message -->
                <div class="bg-background/subtle border border-border rounded-lg p-8 text-center">
                    <div class="max-w-md mx-auto">
                        <svg class="w-16 h-16 text-foreground/40 mx-auto mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-foreground mb-2">No Schedule for Today</h3>
                        <p class="text-foreground/70">
                            You don't have any scheduled sessions for today. Check back later or contact your therapist
                            if you have any questions.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
