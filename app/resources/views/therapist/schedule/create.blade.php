<x-app-layout>
    <x-slot name="title">
        Create Schedule
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/60">Therapist · Schedule</p>
                    <h1 class="text-2xl font-semibold text-foreground">Create New Schedule</h1>
                    <p class="text-sm text-foreground/60 mt-1">
                        Create a single schedule for a student with an active SSA.
                    </p>
                </div>
                <a href="{{ route('therapist.schedule.calendar') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Back to Calendar
                </a>
            </div>

            @include('therapist.schedule._form', [
                'schedule' => null,
                'ssa' => $ssa ?? null,
                'selectedDate' => $selectedDate,
                'serviceOptions' => $serviceOptions,
                'preselectedService' => $preselectedService,
                'preselectedStudent' => $preselectedStudent,
                'studentServiceMappings' => $studentServiceMappings,
                'isEdit' => false,
                'therapistTimezoneLabel' => $therapistTimezoneLabel ?? null,
            ])
        </div>
    </div>

    <x-slot name="scripts">
        <script type="application/json" id="student-service-mappings">
            {!! $studentServiceMappings->toJson() !!}
        </script>
        <script type="application/json" id="service-options-json">
            {!! $serviceOptions->toJson() !!}
        </script>
        @if ($ssa)
            <script type="application/json" id="ssa-services-json">
                {!! $serviceOptions->toJson() !!}
            </script>
        @endif
        <script type="application/json" id="schedule-create-state">
            {!! json_encode([
                'selected_students' => $preselectedStudent ? [$preselectedStudent->id] : [],
                'selected_service' => old('service_id', $preselectedService?->id),
            ]) !!}
        </script>
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-create.js', 'resources/js/pages/therapist-schedule-time.js', 'resources/js/pages/therapist-schedule-recurrence.js'])
    </x-slot>
</x-app-layout>
