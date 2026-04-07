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
                <button id="addScheduleButton" type="button" data-create-base="{{ route('therapist.schedule.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                    @if ($activeSSAs->count() === 0) disabled title="No active SSAs available" @endif>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Schedule
                </button>
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

            {{-- SSA Selection Modal --}}
            <div id="ssaSelectionModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-background rounded-lg shadow-xl max-w-md w-full mx-4">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-foreground mb-4">Select Active SSA</h3>
                        <p class="text-sm text-foreground/60 mb-4">Choose an active SSA to create a schedule for.</p>
                        @if ($activeSSAs->count() > 0)
                            <form id="ssaSelectionForm">
                                <div class="mb-4">
                                    <label for="ssa_id" class="block text-sm font-medium text-foreground mb-2">SSA *</label>
                                    <select id="ssa_id" name="ssa_id" data-select-box class="w-full" required>
                                        <option value="">Select an SSA</option>
                                        @foreach ($activeSSAs as $ssa)
                                            <option value="{{ $ssa->id }}">
                                                SSA #{{ $ssa->id }} - {{ $ssa->student->name ?? 'N/A' }}
                                                ({{ $ssa->primaryService->name ?? 'N/A' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex justify-end gap-3">
                                    <button type="button" id="cancelSSASelection"
                                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                                        Continue
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="mb-4">
                                <p class="text-sm text-foreground/70">You don't have any active SSAs. Please contact your administrator to assign active SSAs.</p>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" id="cancelSSASelection"
                                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                                    Close
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-fullcalendar.js'])
    </x-slot>
</x-app-layout>
