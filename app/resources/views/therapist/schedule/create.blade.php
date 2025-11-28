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
                        Build single or recurring group sessions linked to student SSAs.
                    </p>
                </div>
                <a href="{{ route('therapist.schedule.calendar') }}"
                    class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                    Back to Calendar
                </a>
            </div>

            <form method="POST" action="{{ route('therapist.schedule.store') }}" id="scheduleCreateForm"
                class="space-y-6">
                @csrf

                {{-- Section 1: Schedule Details --}}
                <x-ui::card class="p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Schedule Details</h2>
                            <p class="text-sm text-foreground/60">Choose date, time, and timezone.</p>
                        </div>
                        <span class="text-sm text-foreground/60">All times shown in US/Central</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="schedule_date" value="Schedule Date *" />
                            <x-text-input id="schedule_date" name="schedule_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('schedule_date', $selectedDate->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('schedule_date')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_time" value="Start Time *" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('start_time', '09:00') }}" required />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_time" value="End Time *" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('end_time', '10:00') }}" required />
                                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </x-ui::card>

                {{-- Section 2: Participants --}}
                <x-ui::card class="p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Participants</h2>
                            <p class="text-sm text-foreground/60">Select students and a service available via their
                                active SSAs.</p>
                        </div>
                        <span class="text-xs text-foreground/60" id="groupBadge" style="display: none;">
                            Group session enabled
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="student_ids" value="Students *" />
                            @php
                                $oldStudents = old('student_ids', []);
                            @endphp
                            <select id="student_ids" name="student_ids[]" multiple data-select-box
                                class="mt-1 block w-full" required>
                                @foreach ($students as $student)
                                    <option value="{{ $student->user_id }}" @selected(in_array($student->user_id, $oldStudents))>
                                        {{ $student->first_name }} {{ $student->last_name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-foreground/60 mt-1">
                                Only students linked to your active SSAs are shown.
                            </p>
                            <x-input-error :messages="$errors->get('student_ids')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="service_id" value="Service *" />
                            <select id="service_id" name="service_id" data-select-box class="mt-1 block w-full" required
                                data-initial-value="{{ old('service_id') }}" disabled>
                                <option value="">Select a service after choosing students</option>
                            </select>
                            <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                        </div>
                    </div>
                </x-ui::card>

                {{-- Section 3: Recurrence --}}
                <x-ui::card class="p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Recurrence</h2>
                            <p class="text-sm text-foreground/60">Create repeating sessions with an explicit occurrence
                                count.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="recurrence_type" value="Recurrence Type" />
                            <select id="recurrence_type" name="recurrence_type" class="mt-1 block w-full">
                                <option value="none" @selected(old('recurrence_type', 'none') === 'none')>Does not repeat</option>
                                <option value="daily" @selected(old('recurrence_type') === 'daily')>Daily</option>
                                <option value="weekly" @selected(old('recurrence_type') === 'weekly')>Weekly</option>
                                <option value="bi_weekly" @selected(old('recurrence_type') === 'bi_weekly')>Bi-weekly</option>
                                <option value="monthly" @selected(old('recurrence_type') === 'monthly')>Monthly</option>
                            </select>
                            <x-input-error :messages="$errors->get('recurrence_type')" class="mt-2" />
                        </div>

                        <div id="occurrenceCountWrapper" style="display: none;">
                            <x-input-label for="occurrence_count" value="Number of Occurrences *" />
                            <x-text-input id="occurrence_count" name="occurrence_count" type="number" min="2"
                                class="mt-1 block w-full" value="{{ old('occurrence_count') }}" />
                            <x-input-error :messages="$errors->get('occurrence_count')" class="mt-2" />
                        </div>

                        <div id="recurrenceEndWrapper" style="display: none;">
                            <x-input-label value="Projected End Date" />
                            <p class="text-sm text-foreground/80 mt-2" id="recurrence_end_preview">
                                Select recurrence type and occurrence count to preview end date.
                            </p>
                        </div>
                    </div>
                </x-ui::card>

                {{-- Section 4: Notes & Summary --}}
                <x-ui::card class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Notes & Review</h2>
                            <p class="text-sm text-foreground/60">Add optional notes and review selections.</p>
                        </div>
                    </div>

                    <textarea name="notes" id="notes" rows="4"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm" placeholder="Notes (optional)">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />

                    <div class="bg-background/subtle rounded-lg p-4 text-sm text-foreground/80" id="summaryPanel">
                        <p class="font-medium mb-2">Summary:</p>
                        <ul class="space-y-1 text-foreground/70">
                            <li id="summaryDate">Date: {{ $selectedDate->format('F j, Y') }}</li>
                            <li id="summaryStudents">Students: —</li>
                            <li id="summaryService">Service: —</li>
                            <li id="summaryRecurrence">Recurrence: Does not repeat</li>
                        </ul>
                    </div>
                </x-ui::card>

                <input type="hidden" id="recurrence_end_date" name="recurrence_end_date"
                    value="{{ old('recurrence_end_date') }}">

                <div class="flex justify-end gap-3">
                    <a href="{{ route('therapist.schedule.calendar') }}"
                        class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-slot name="scripts">
        <script type="application/json" id="student-service-mappings">
            {!! $studentServiceMappings->toJson() !!}
        </script>
        <script type="application/json" id="service-options-json">
            {!! $serviceOptions->toJson() !!}
        </script>
        <script type="application/json" id="schedule-create-state">
            {!! json_encode([
                'selected_students' => $oldStudents,
                'selected_service' => old('service_id'),
                'recurrence_type' => old('recurrence_type', 'none'),
                'occurrence_count' => old('occurrence_count'),
            ]) !!}
        </script>
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-create.js'])
    </x-slot>
</x-app-layout>
