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
            ])
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-time.js'])
    </x-slot>
</x-app-layout>
