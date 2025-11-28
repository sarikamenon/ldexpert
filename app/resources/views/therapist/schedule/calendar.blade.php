<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/therapist-schedule.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            {{-- Header --}}
            <x-schedule.schedule-header />

            {{-- Pending Sessions Banner --}}
            <x-schedule.pending-banner :count="$pendingCount" :pending-url="route('therapist.schedule.pending')" />

            {{-- Main Content --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Schedule Overview</h1>
                    <p class="text-sm text-foreground/60">Select a date to review or add sessions.</p>
                </div>
                <a id="addScheduleButton"
                    href="{{ route('therapist.schedule.create', ['date' => $selectedDate->format('Y-m-d')]) }}"
                    data-create-base="{{ route('therapist.schedule.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    + Add New Schedule
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left Panel: Calendar and Filters --}}
                <div class="lg:col-span-1 space-y-6">
                    {{-- Calendar --}}
                    <x-schedule.calendar :selected-date="$selectedDate" />

                    {{-- Filters --}}
                    <x-schedule.schedule-filters :schools="$schools" :students="$students" :selected-school-id="$selectedSchoolId"
                        :selected-student-id="$selectedStudentId" />
                </div>

                {{-- Right Panel: Schedule Details --}}
                <div class="lg:col-span-2">
                    <x-ui::card class="p-6">
                        {{-- Date Header --}}
                        <x-schedule.schedule-details-header :selected-date="$selectedDate" />

                        {{-- Schedule List --}}
                        <x-schedule.schedule-list :schedules="$schedules" :selected-date="$selectedDate"
                            add-button-url="{{ route('therapist.schedule.create', ['date' => $selectedDate->format('Y-m-d')]) }}"
                            id="scheduleList" data-schedule-url="{{ route('therapist.schedule.schedules') }}"
                            data-create-url="{{ route('therapist.schedule.create') }}" />
                    </x-ui::card>
                </div>
            </div>
        </div>
    </div>
    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-calendar.js'])
    </x-slot>
</x-app-layout>
