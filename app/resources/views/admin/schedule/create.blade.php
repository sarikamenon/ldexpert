<x-admin.layouts.app>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Schedule</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Creating a schedule for
                    <span class="font-medium text-foreground">{{ $therapistName }}</span>
                    · SSA #{{ $ssa->id }}
                </p>
            </div>
            <a href="{{ route('admin.schedule-calendar.index') }}"
                class="inline-flex items-center gap-2 text-sm text-foreground/60 hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Calendar
            </a>
        </div>
    </div>

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-6">{{ session('error') }}</x-ui::alert>
    @endif

    @include('admin.schedule._form', [
        'schedule'               => null,
        'ssa'                    => $ssa,
        'selectedDate'           => $selectedDate,
        'serviceOptions'         => $serviceOptions,
        'preselectedService'     => $preselectedService ?? null,
        'preselectedStudent'     => $preselectedStudent ?? null,
        'studentServiceMappings' => $studentServiceMappings,
        'isEdit'                 => false,
        'therapistTimezoneLabel' => $therapistTimezoneLabel ?? null,
        'isPrivateStudent'       => $isPrivateStudent ?? false,
        'allowsWeekendScheduling'=> $allowsWeekendScheduling ?? false,
        'weekDays'               => $weekDays,
        'holidayDates'           => $holidayDates ?? [],
        'formAction'             => route('admin.schedule.store'),
        'therapistId'            => $therapistId,
        'defaultMeetingLocation' => $defaultMeetingLocation ?? null,
    ])

    <x-slot name="scripts">
        <script type="application/json" id="student-service-mappings">
            {!! $studentServiceMappings->toJson() !!}
        </script>
        <script type="application/json" id="service-options-json">
            {!! $serviceOptions->toJson() !!}
        </script>
        <script type="application/json" id="ssa-services-json">
            {!! $serviceOptions->toJson() !!}
        </script>
        <script type="application/json" id="schedule-create-state">
            {!! json_encode([
                'selected_students' => $preselectedStudent ? [$preselectedStudent->id] : [],
                'selected_service'  => old('service_id', $preselectedService?->id),
                'is_private_student'=> $isPrivateStudent ?? false,
            ]) !!}
        </script>
        @vite(['resources/css/therapist-schedule.css', 'resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-create.js', 'resources/js/pages/therapist-schedule-time.js', 'resources/js/pages/therapist-schedule-recurrence.js'])
    </x-slot>
</x-admin.layouts.app>
