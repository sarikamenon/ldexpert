<x-app-layout>
    <x-slot name="title">
        Edit Schedule
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/60">Therapist · Schedule</p>
                    <h1 class="text-2xl font-semibold text-foreground">Edit Schedule</h1>
                    <p class="text-sm text-foreground/60 mt-1">
                        Update schedule date, time, location, and notes.
                    </p>
                </div>
                <a href="{{ route('therapist.schedule-calendar.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Back to Calendar
                </a>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success">{{ session('status') }}</x-ui::alert>
            @endif

            @if (session('warning'))
                <x-ui::alert variant="warning">
                    <div class="flex items-start gap-2">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <div>
                            <p class="font-medium text-sm">Sub request not created</p>
                            <p class="text-sm mt-0.5">{{ session('warning') }} You can raise the request from the Sub Coverage section below once the schedule is within the allowed window.</p>
                        </div>
                    </div>
                </x-ui::alert>
            @endif

            @include('therapist.schedule._form', [
                'schedule' => $schedule,
                'ssa' => null,
                'selectedDate' => null,
                'serviceOptions' => collect(),
                'preselectedService' => null,
                'preselectedStudent' => null,
                'studentServiceMappings' => collect(),
                'isEdit' => true,
                'therapistTimezoneLabel' => $therapistTimezoneLabel ?? null,
                'isPrivateStudent' => $isPrivateStudent,
                'allowsWeekendScheduling' => $allowsWeekendScheduling ?? false,
                'weekDays' => $weekDays,
                'holidayDates' => $holidayDates ?? [],
                'scheduleLocalDate' => $scheduleLocalDate,
                'scheduleLocalDateFormatted' => $scheduleLocalDateFormatted,
                'scheduleLocalStartTime' => $scheduleLocalStartTime,
                'scheduleLocalEndTime' => $scheduleLocalEndTime,
                'subPanel' => $subPanel ?? null,
                'makeupRequestId' => $makeupRequestId ?? null,
            ])
        </div>
    </div>

    <x-slot name="scripts">
        @vite([
            'resources/js/common/select-box.js',
            'resources/js/pages/therapist-schedule-time.js',
            'resources/js/pages/therapist-schedule-recurrence.js',
            'resources/js/pages/therapist-schedule-sub-coverage.js',
        ])
    </x-slot>
</x-app-layout>
