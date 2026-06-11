<x-admin.layouts.app>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Edit Schedule</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Editing schedule for
                    <span class="font-medium text-foreground">{{ $schedule->therapist?->name ?? 'Unknown Therapist' }}</span>
                </p>
            </div>
            <a href="{{ route('admin.schedule-calendar.index') }}"
                class="inline-flex items-center gap-2 text-sm text-foreground/60 hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Calendar
            </a>
        </div>
    </div>

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-6">{{ session('status') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-6">{{ session('error') }}</x-ui::alert>
    @endif

    @include('admin.schedule._form', [
        'schedule'               => $schedule,
        'ssa'                    => null,
        'selectedDate'           => null,
        'serviceOptions'         => collect(),
        'preselectedService'     => null,
        'preselectedStudent'     => null,
        'studentServiceMappings' => collect(),
        'isEdit'                 => true,
        'therapistTimezoneLabel' => $therapistTimezoneLabel ?? null,
        'isPrivateStudent'       => $isPrivateStudent,
        'allowsWeekendScheduling'=> $allowsWeekendScheduling ?? false,
        'weekDays'               => $weekDays,
        'holidayDates'           => $holidayDates ?? [],
        'scheduleLocalDate'      => $scheduleLocalDate,
        'scheduleLocalDateFormatted' => $scheduleLocalDateFormatted,
        'scheduleLocalStartTime' => $scheduleLocalStartTime,
        'scheduleLocalEndTime'   => $scheduleLocalEndTime,
        'occurrenceRows'         => $occurrenceRows ?? [],
        'formAction'             => route('admin.schedule.update', $schedule->id),
        'therapistId'            => null,
    ])

    <x-slot name="scripts">
        @vite(['resources/css/therapist-schedule.css', 'resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-create.js', 'resources/js/pages/therapist-schedule-time.js', 'resources/js/pages/therapist-schedule-recurrence.js'])
    </x-slot>
</x-admin.layouts.app>
