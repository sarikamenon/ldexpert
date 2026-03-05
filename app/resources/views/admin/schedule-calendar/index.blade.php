<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/fullcalendar-custom.css'])
    </x-slot>

    <x-page-title title="Schedule Calendar" description="View all therapist and student schedules by day, week, or month." />

    <x-ui::card class="p-6">
        {{-- Filters --}}
        <div id="scheduleCalendarFilters" class="flex flex-wrap gap-3 items-end mb-6">
            <div>
                <label for="filter_therapist_id" class="block text-xs font-medium text-foreground/70 mb-1">Therapist</label>
                <select id="filter_therapist_id" name="therapist_id" data-select-box data-placeholder="All Therapists"
                    class="w-48">
                    <option value="">All Therapists</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
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
        </div>

        {{-- Calendar --}}
        <div id="fullCalendar"
            data-events-url="{{ route('admin.schedule-calendar.events') }}"
            data-details-url="{{ url('/admin/schedule-calendar') }}">
        </div>
    </x-ui::card>

    {{-- Schedule Details Modal --}}
    <x-schedule.schedule-details-modal />

    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/admin-schedule-calendar.js'])
    </x-slot>
</x-admin.layouts.app>
