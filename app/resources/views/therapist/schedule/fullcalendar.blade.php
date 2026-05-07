<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/fullcalendar-custom.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Schedule Calendar</h1>
                    <p class="text-sm text-foreground/60">View your schedules in day, week, or month format.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('therapist.session-logs.select-ssa') }}" target="_blank" rel="noopener"
                        class="inline-flex items-center px-4 py-2 border border-primary text-primary rounded-lg hover:bg-primary/5 text-sm font-medium transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Indirect Service
                    </a>
                    <button id="addScheduleButton" type="button" data-create-base="{{ route('therapist.schedule.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                        @if ($activeSSAs->count() === 0) disabled title="No active SSAs available" @endif>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New Schedule
                    </button>
                </div>
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

                <x-schedule.calendar-legend />

                {{-- Calendar --}}
                <div id="fullCalendar"
                    data-events-url="{{ route('therapist.schedule-calendar.events') }}">
                </div>
            </x-ui::card>

            {{-- Schedule Details Modal --}}
            <x-schedule.schedule-details-modal />

            {{-- SSA Selection Modal --}}
            <x-schedule.ssa-selection-modal :activeSSAs="$activeSSAs" />
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-fullcalendar.js'])
    </x-slot>
</x-app-layout>
