@props([
    'schedule'              => null,
    'ssa'                   => null,
    'selectedDate'          => null,
    'serviceOptions'        => collect(),
    'preselectedService'    => null,
    'preselectedStudent'    => null,
    'studentServiceMappings'=> collect(),
    'isEdit'                => false,
    'therapistTimezoneLabel'=> null,
    'isPrivateStudent'      => false,
    'allowsWeekendScheduling'=> false,
    'weekDays'              => [],
    'holidayDates'          => [],
    'occurrenceRows'        => [],
    'formAction'            => '',
    'therapistId'           => null,
    'defaultMeetingLocation'=> null,
])

@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
    $errors = $errors ?? session('errors', new \Illuminate\Support\ViewErrorBag());
@endphp

<form method="POST"
    action="{{ $formAction }}"
    id="{{ $isEdit ? 'scheduleEditForm' : 'scheduleCreateForm' }}"
    class="space-y-6"
    data-allow-weekend-scheduling="{{ $allowsWeekendScheduling ? '1' : '0' }}"
    data-holiday-dates="@json($holidayDates)">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Hidden therapist_id (only needed on create; on edit the controller resolves from the schedule) --}}
    @if (! $isEdit && $therapistId)
        <input type="hidden" name="therapist_id" value="{{ $therapistId }}">
    @endif

    {{-- Section 1: Schedule Details --}}
    <x-ui::card class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Schedule Details</h2>
                <p class="text-sm text-foreground/60">
                    {{ $isEdit ? 'Update date and time.' : 'Choose date, time, and timezone.' }}
                </p>
            </div>
            <span class="text-sm text-foreground/60">All times in {{ $therapistTimezoneLabel ?? 'US/Central' }}</span>
        </div>

        @php $currentSsa = $isEdit ? $schedule->ssa : $ssa; @endphp
        @if ($currentSsa)
            <div class="bg-background/subtle rounded-lg p-4 space-y-3 border border-border">
                <h3 class="text-sm font-semibold text-foreground">SSA #{{ $currentSsa->id }} Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Student:</span>
                            <span class="font-medium text-foreground">
                                @if ($currentSsa->student)
                                    <a href="{{ route('admin.students.show', $currentSsa->student) }}"
                                        class="text-primary hover:underline">
                                        {{ $currentSsa->student->name }}
                                    </a>
                                @else
                                    N/A
                                @endif
                                @if ($currentSsa->student?->studentProfile?->timezone)
                                    <span class="text-foreground/70">
                                        · {{ \App\Constants\UsTimezones::getTimezoneLabel($currentSsa->student->studentProfile->timezone) }}
                                    </span>
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">School/Family:</span>
                            <span class="font-medium text-foreground">
                                {{ $currentSsa->student?->studentProfile?->school?->display_name ?? 'N/A' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">Start Date:</span>
                            <span class="font-medium text-foreground">{{ $currentSsa->start_date->format('M d, Y') }}</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Primary Service:</span>
                            <span class="font-medium text-foreground">{{ $currentSsa->primaryService->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">End Date:</span>
                            <span class="font-medium text-foreground">{{ $currentSsa->end_date->format('M d, Y') }}</span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">Status:</span>
                            <span class="font-medium text-foreground">{{ $currentSsa->status->label() }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="service_id" value="Service *" />
                    @if ($isEdit)
                        <x-ui::input id="service_id" type="text"
                            class="mt-1 block w-full bg-background/subtle cursor-not-allowed opacity-75"
                            value="{{ $schedule->service?->name ?? 'N/A' }}" disabled readonly />
                        <input type="hidden" name="service_id" value="{{ $schedule->service_id }}">
                        <p class="text-xs text-foreground/60 mt-1">Service cannot be changed after creation.</p>
                    @else
                        <select id="service_id" name="service_id" data-select-box class="mt-1 block w-full" required
                            data-initial-value="{{ old('service_id', $preselectedService?->id) }}">
                            <option value="">Select a service</option>
                            @foreach ($serviceOptions as $service)
                                <option value="{{ $service['service_id'] }}"
                                    @selected(old('service_id', $preselectedService?->id) == $service['service_id'])
                                    data-is-primary="{{ $service['is_primary'] ? '1' : '0' }}">
                                    {{ $service['service_name'] }}@if ($service['is_primary']) (Primary)@endif
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="ssa_id" value="{{ $currentSsa->id }}">
                        <input type="hidden" name="student_ids[]" value="{{ $preselectedStudent->id }}">
                        <p class="text-xs text-foreground/60 mt-1">Services available for this SSA.</p>
                    @endif
                    <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="duration_minutes" value="Duration (minutes) *" />
                    <x-ui::input id="duration_minutes" name="duration_minutes" type="number" class="mt-1 block w-full"
                        min="{{ config('session_minutes.min') }}" max="{{ config('session_minutes.max') }}"
                        step="1"
                        value="{{ old('duration_minutes', $isEdit ? $schedule->durationMinutes() : $currentSsa?->minutes_per_session ?? 60) }}"
                        required />
                    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="schedule_date" value="Schedule Date *" />
                @if ($isEdit)
                    <x-ui::input id="schedule_date" name="schedule_date" type="date" class="mt-1 block w-full"
                        value="{{ old('schedule_date', $scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) }}"
                        min="{{ now()->format('Y-m-d') }}" required />
                @else
                    <x-ui::input id="schedule_date" name="schedule_date" type="date" class="mt-1 block w-full"
                        value="{{ old('schedule_date', $selectedDate?->format('Y-m-d')) }}" required />
                @endif
                <x-input-error :messages="$errors->get('schedule_date')" class="mt-2" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="start_time" value="Start Time *" />
                    <x-ui::input id="start_time" name="start_time" type="time" class="mt-1 block w-full"
                        value="{{ old('start_time', $isEdit ? ($scheduleLocalStartTime ?? $schedule->start_time?->format('H:i')) : '09:00') }}"
                        required />
                    <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="end_time_display" value="End Time (auto)" />
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <x-ui::input id="end_time_display" type="time"
                            class="block w-full min-w-0 flex-1 bg-background/subtle cursor-not-allowed opacity-75"
                            value="" disabled readonly tabindex="-1" />
                        <span id="end_time_next_day" class="hidden shrink-0 text-sm text-foreground/70">(next day)</span>
                    </div>
                </div>
            </div>
        </div>
        @if (! $isEdit)
            <p class="text-xs text-foreground/60" style="margin-top:0;">
                Enter the schedule date and time in the <span class="font-medium">therapist's</span> timezone.
            </p>
        @endif
    </x-ui::card>

    {{-- Section 2: Recurrence Options --}}
    @php
        $currentRecurrenceType = $isEdit
            ? old('recurrence_type', $schedule->recurrence_type?->value ?? 'none')
            : old('recurrence_type', 'none');
        $currentRecurrenceEndDate = $isEdit
            ? old('recurrence_end_date', $schedule->recurrence_end_date?->format('Y-m-d') ?? '')
            : old('recurrence_end_date', '');
        $originalRecurrenceType    = $isEdit ? ($schedule->recurrence_type?->value ?? 'none') : null;
        $originalRecurrenceEndDate = $isEdit ? ($schedule->recurrence_end_date?->format('Y-m-d') ?? '') : null;
    @endphp

    <x-ui::card class="p-6 space-y-6" id="recurrence_card">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Recurrence Options</h2>
            <p class="text-sm text-foreground/60">
                @if ($isEdit) Update how often this schedule repeats.
                @else Create a recurring schedule that repeats at regular intervals.
                @endif
            </p>
        </div>

        @if ($isEdit)
            <div id="recurrence_change_warning" class="hidden rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-foreground"
                role="alert"
                data-original-recurrence-type="{{ $originalRecurrenceType }}"
                data-original-recurrence-end-date="{{ $originalRecurrenceEndDate }}"
                data-schedule-date="{{ $scheduleLocalDateFormatted ?? $schedule->schedule_date?->format('M d, Y') }}">
                <div class="flex items-start gap-2">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p>
                        <span class="font-medium">Recurrence changed.</span>
                        Saving will delete and regenerate all unbilled future sessions from
                        <span class="font-medium">{{ $scheduleLocalDateFormatted ?? $schedule->schedule_date?->format('M d, Y') }}</span> onward.
                    </p>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <x-input-label for="recurrence_type" value="Recurrence Type *" />
                <select id="recurrence_type" name="recurrence_type" data-select-box class="mt-1 block w-full"
                    @if (! $isEdit) required @endif>
                    @if (! $isEdit)<option value="">Select recurrence type</option>@endif
                    @foreach (\App\Enums\RecurrenceType::cases() as $recurrenceType)
                        <option value="{{ $recurrenceType->value }}" @selected($currentRecurrenceType === $recurrenceType->value)>
                            {{ $recurrenceType->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('recurrence_type')" class="mt-2" />
            </div>

            <div id="weekly_days_container" class="hidden">
                <x-input-label value="Days of the Week *" />
                <p class="text-xs text-foreground/60 mt-1 mb-3" id="weekly_days_help">
                    Select which days each week the student will be seen.
                </p>
                <div class="flex flex-wrap gap-2" aria-describedby="weekly_days_help">
                    @php $oldDays = old('weekly_days', []); @endphp
                    @foreach ($weekDays as $day)
                        <label class="weekly-day-label flex items-center gap-1.5 cursor-pointer rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-primary/10 hover:border-primary/50 select-none">
                            <input type="checkbox" name="weekly_days[]" value="{{ $day->value }}"
                                class="sr-only weekly-day-checkbox"
                                @checked(in_array($day->value, (array) $oldDays)) />
                            <svg class="weekly-day-check w-0 h-3.5 opacity-0 transition-all overflow-hidden shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ $day->shortLabel() }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('weekly_days')" class="mt-2" />
            </div>

            <div id="recurrence_end_date_container" class="{{ $currentRecurrenceType && $currentRecurrenceType !== 'none' ? '' : 'hidden' }}">
                <x-input-label for="recurrence_end_date" value="Recurrence End Date *" />
                <x-ui::input id="recurrence_end_date" name="recurrence_end_date" type="date"
                    class="mt-1 block w-full"
                    value="{{ $currentRecurrenceEndDate }}"
                    min="{{ old('schedule_date', $isEdit ? ($scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) : ($selectedDate?->format('Y-m-d') ?? now()->format('Y-m-d'))) }}" />
                <p class="text-xs text-foreground/60 mt-1">Must be after the schedule start date.</p>
                <x-input-error :messages="$errors->get('recurrence_end_date')" class="mt-2" />
            </div>

            @if ($isEdit)
                <input type="hidden" name="occurrences_regenerated" id="occurrences_regenerated" value="0" />
            @endif

            <div id="occurrence_dates_container" class="hidden mt-4"
                @if ($isEdit) data-edit-mode="1" data-occurrence-rows="{{ json_encode($occurrenceRows) }}" @endif>
                <x-input-label value="Occurrence Dates *" />
                <p class="text-xs text-foreground/60 mt-1 mb-3">
                    @if ($isEdit)
                        Edit any session's date or time below, or remove unwanted occurrences with the ✕ button.
                        A session whose time differs from the series stays in the series as a modified session.
                    @else
                        Review and adjust generated occurrence dates. Remove any you don't want.
                    @endif
                </p>
                <x-input-error :messages="$errors->get('occurrence_dates')" class="mt-2" />
                <x-input-error :messages="$errors->get('occurrence_dates.*')" class="mt-2" />
                <x-input-error :messages="$errors->get('occurrence_start_times.*')" class="mt-2" />
                <x-input-error :messages="$errors->get('occurrence_end_times.*')" class="mt-2" />
            </div>

            <div id="additional_dates_container" class="hidden mt-4">
                <x-input-label value="Additional Dates" />
                <p class="text-xs text-foreground/60 mt-1 mb-3" id="additional_dates_help">
                    One-off extra sessions outside the weekly pattern.
                </p>
                <div id="additional_dates_list" class="space-y-3" aria-describedby="additional_dates_help"></div>
                <button type="button" id="add_additional_date_btn"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-primary/40 px-3 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add another date
                </button>
            </div>
        </div>
    </x-ui::card>

    {{-- Section 3: Location & Meeting Details --}}
    <x-ui::card class="p-6 space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Location & Meeting Details</h2>
            <p class="text-sm text-foreground/60">
                {{ $isEdit ? 'Update meeting details or location.' : 'Add meeting details or location for this session.' }}
            </p>
        </div>
        <div>
            <x-input-label for="location_details" value="Location/Meeting Details *" />
            <textarea name="location_details" id="location_details" rows="4"
                class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm"
                placeholder="Enter meeting link (e.g., Google Meet, Zoom), location address, or other details..."
                required>{{ old('location_details', $isEdit ? $schedule->location_details : $defaultMeetingLocation) }}</textarea>
            <x-input-error :messages="$errors->get('location_details')" class="mt-2" />
        </div>
    </x-ui::card>

    {{-- Section 4: Notes --}}
    <x-ui::card class="p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Notes</h2>
            <p class="text-sm text-foreground/60">Optional notes for this schedule.</p>
        </div>
        <textarea name="notes" id="notes" rows="4"
            class="w-full border border-border rounded-lg px-3 py-2 text-sm"
            placeholder="Notes (optional)">{{ old('notes', $isEdit ? $schedule->notes : '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.schedule-calendar.index') }}">
            <x-ui::button variant="secondary">Cancel</x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Schedule' : 'Create Schedule' }}
        </x-ui::button>
    </div>
</form>
