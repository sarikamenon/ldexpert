<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/therapist-schedule.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            {{-- Pending Sessions Banner --}}
            <x-schedule.pending-banner :count="$pendingCount" :pending-url="route('therapist.schedule.pending')" />

            {{-- Main Content --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Schedule Overview</h1>
                    <p class="text-sm text-foreground/60">Select a date to review or add sessions.</p>
                </div>
                <button id="addScheduleButton" type="button" data-create-base="{{ route('therapist.schedule.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                    @if ($activeSSAs->count() === 0) disabled title="No active SSAs available" @endif>
                    + Add New Schedule
                </button>
            </div>

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
                                    <label for="ssa_id" class="block text-sm font-medium text-foreground mb-2">SSA
                                        *</label>
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
                                <p class="text-sm text-foreground/70">You don't have any active SSAs. Please contact
                                    your administrator to assign active SSAs.</p>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left Panel: Calendar and Filters --}}
                <div class="lg:col-span-1 space-y-6">
                    {{-- Calendar --}}
                    <x-schedule.calendar :selected-date="$selectedDate"
                        data-therapist-timezone-label="{{ $therapistTimezoneLabel ?? 'Central Time (CT)' }}" />

                    {{-- Filters -- Hidden for now, will be enabled later --}}
                    {{-- <x-schedule.schedule-filters :schools="$schools" :students="$students" :selected-school-id="$selectedSchoolId"
                        :selected-student-id="$selectedStudentId" /> --}}
                </div>

                {{-- Right Panel: Schedule Details --}}
                <div class="lg:col-span-2">
                    <x-ui::card class="p-6">
                        {{-- Date Header --}}
                        <x-schedule.schedule-details-header :selected-date="$selectedDate" :timezone="$therapistTimezone ?? 'America/Chicago'" :timezone-label="$therapistTimezoneLabel ?? 'Central Time (CT)'" />

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
