<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/fullcalendar-custom.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-foreground">Schedule Calendar</h1>
                <p class="text-sm text-foreground/60">View your schedules in day, week, or month format.</p>
            </div>

            <x-ui::card class="p-6">
                {{-- Filters --}}
                <div id="scheduleCalendarFilters" class="flex flex-wrap gap-3 items-end mb-6">
                    <div>
                        <label for="filter_student_ids" class="block text-xs font-medium text-foreground/70 mb-1">Student</label>
                        <select id="filter_student_ids" name="student_ids[]" data-select-box data-placeholder="All Students"
                            multiple class="w-64">
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter_status" class="block text-xs font-medium text-foreground/70 mb-1">Status</label>
                        <select id="filter_status" name="status" data-select-box data-placeholder="All Statuses" class="w-36">
                            <option value="">All Statuses</option>
                            @foreach (\App\Enums\ScheduleStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter_billing_status" class="block text-xs font-medium text-foreground/70 mb-1">Billing</label>
                        <select id="filter_billing_status" name="billing_status" data-select-box data-placeholder="All Billing"
                            class="w-36">
                            <option value="">All Billing</option>
                            @foreach (\App\Enums\BillingStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-ui::button type="button" id="applyCalendarFilters">Filter</x-ui::button>
                        <x-ui::button type="button" variant="secondary" id="clearCalendarFilters">Clear</x-ui::button>
                    </div>
                </div>

                {{-- Calendar --}}
                <div id="fullCalendar"
                    data-events-url="{{ route('therapist.schedule-calendar.events') }}">
                </div>
            </x-ui::card>

            {{-- Schedule Details Modal --}}
            <x-schedule.schedule-details-modal />
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-fullcalendar.js'])
    </x-slot>
</x-app-layout>
