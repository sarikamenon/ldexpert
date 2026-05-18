@props([
    'schedule' => null,
    'ssa' => null,
    'selectedDate' => null,
    'serviceOptions' => collect(),
    'preselectedService' => null,
    'preselectedStudent' => null,
    'studentServiceMappings' => collect(),
    'isEdit' => false,
    'isPrivateStudent' => false,
    'subPanel' => null,
])

@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
    $errors = $errors ?? session('errors', new \Illuminate\Support\ViewErrorBag());
@endphp

<form method="POST"
    action="{{ $isEdit ? route('therapist.schedule.update', $schedule->id) : route('therapist.schedule.store') }}"
    id="{{ $isEdit ? 'scheduleEditForm' : 'scheduleCreateForm' }}" class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Section 1: Schedule Details --}}
    <x-ui::card class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Schedule Details</h2>
                <p class="text-sm text-foreground/60">
                    {{ $isEdit ? 'Update date and time.' : 'Choose date, time, and timezone.' }}</p>
            </div>
            <span class="text-sm text-foreground/60">All times shown in
                {{ $therapistTimezoneLabel ?? 'US/Central' }}</span>
        </div>

        {{-- SSA and Student Information (for both create and edit) --}}
        @php
            $currentSsa = $isEdit ? $schedule->ssa : $ssa;
        @endphp
        @if ($currentSsa)
            <div class="bg-background/subtle rounded-lg p-4 space-y-3 border border-border">
                <h3 class="text-sm font-semibold text-foreground">SSA #{{ $currentSsa->id }} Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    {{-- Left Side --}}
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Student:</span>
                            <span class="font-medium text-foreground">
                                @if ($currentSsa->student)
                                    <a href="{{ route('therapist.students.show', $currentSsa->student) }}"
                                        class="text-primary hover:underline">
                                        {{ $currentSsa->student->name }}
                                    </a>
                                @else
                                    N/A
                                @endif
                                @if ($currentSsa->student?->studentProfile?->timezone)
                                    <span class="text-foreground/70">
                                        ·
                                        {{ \App\Constants\UsTimezones::getTimezoneLabel($currentSsa->student->studentProfile->timezone) }}
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
                            <span class="font-medium text-foreground">
                                {{ $currentSsa->start_date->format('M d, Y') }}
                            </span>
                        </div>
                    </div>

                    {{-- Right Side --}}
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Primary Service:</span>
                            <span
                                class="font-medium text-foreground">{{ $currentSsa->primaryService->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">End Date:</span>
                            <span class="font-medium text-foreground">
                                {{ $currentSsa->end_date->format('M d, Y') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Service and Duration Dropdowns (for both create and edit) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="service_id" value="Service *" />
                    @if ($isEdit)
                        {{-- Edit mode: Show service as disabled/read-only --}}
                        <x-ui::input id="service_id" type="text"
                            class="mt-1 block w-full bg-background/subtle cursor-not-allowed opacity-75"
                            value="{{ $schedule->service?->name ?? 'N/A' }}" disabled readonly />
                        <input type="hidden" name="service_id" value="{{ $schedule->service_id }}">
                        <p class="text-xs text-foreground/60 mt-1">
                            Service cannot be changed after schedule creation.
                        </p>
                    @else
                        {{-- Create mode: Editable dropdown --}}
                        <select id="service_id" name="service_id" data-select-box class="mt-1 block w-full" required
                            data-initial-value="{{ old('service_id', $preselectedService?->id) }}">
                            <option value="">Select a service</option>
                            @foreach ($serviceOptions as $service)
                                <option value="{{ $service['service_id'] }}" @selected(old('service_id', $preselectedService?->id) == $service['service_id'])
                                    data-is-primary="{{ $service['is_primary'] ? '1' : '0' }}">
                                    {{ $service['service_name'] }}
                                    @if ($service['is_primary'])
                                        (Primary)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="ssa_id" value="{{ $currentSsa->id }}">
                        <input type="hidden" name="student_ids[]" value="{{ $preselectedStudent->id }}">
                        <p class="text-xs text-foreground/60 mt-1">
                            Services available for this SSA.
                        </p>
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
                    @if ($currentSsa)
                        <p class="text-xs text-foreground/60 mt-1">
                            This defaults from the SSA but can be adjusted for this schedule.
                        </p>
                    @endif
                    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
                </div>
            </div>
        @endif

        {{-- Schedule Date and Time Fields (common for both create and edit) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="schedule_date" value="Schedule Date *" />
                @if ($isEdit)
                    <x-ui::input id="schedule_date" name="schedule_date" type="date" class="mt-1 block w-full"
                        value="{{ old('schedule_date', $scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) }}"
                        min="{{ now()->format('Y-m-d') }}" required />
                @else
                    <x-ui::input id="schedule_date" name="schedule_date" type="date" class="mt-1 block w-full"
                        value="{{ old('schedule_date', $selectedDate?->format('Y-m-d')) }}"
                        required />
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
                    <x-input-label for="end_time_display" value="End Time (auto-calculated)" />
                    
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <x-ui::input id="end_time_display" type="time"
                            class="block w-full min-w-0 flex-1 bg-background/subtle cursor-not-allowed opacity-75"
                            value="" disabled readonly tabindex="-1" aria-describedby="end_time_display_help" />
                        <span id="end_time_next_day" class="hidden shrink-0 text-sm text-foreground/70"
                            aria-live="polite">(next day)</span>
                    </div>
                </div>
            </div>
        </div>
        @if (!$isEdit)
            <p class="text-xs text-foreground/60" style="margin-top: 0;">
                Enter the schedule date and time in <span class="font-medium">your</span> timezone.
                If the student is in a different timezone, the system will handle the conversion for them.
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
        $originalRecurrenceType = $isEdit ? ($schedule->recurrence_type?->value ?? 'none') : null;
        $originalRecurrenceEndDate = $isEdit ? ($schedule->recurrence_end_date?->format('Y-m-d') ?? '') : null;
        $isExistingRecurring = $isEdit && $schedule->isRecurring();
    @endphp

    <x-ui::card class="p-6 space-y-6" id="recurrence_card">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Recurrence Options</h2>
            <p class="text-sm text-foreground/60">
                @if ($isEdit)
                    Update how often this schedule repeats. Changes will regenerate all future sessions from this date.
                @else
                    Create a recurring schedule that repeats at regular intervals.
                @endif
            </p>
        </div>

        {{-- Inline warning banner (edit mode only, shown by JS when recurrence fields change) --}}
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
                        Past and billed sessions will not be affected.
                    </p>
                </div>
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <x-input-label for="recurrence_type" value="Recurrence Type *" />
                <select id="recurrence_type" name="recurrence_type" data-select-box class="mt-1 block w-full"
                    @if (!$isEdit) required @endif>
                    @if (!$isEdit)
                        <option value="">Select recurrence type</option>
                    @endif
                    @foreach (\App\Enums\RecurrenceType::cases() as $recurrenceType)
                        @if ($recurrenceType === \App\Enums\RecurrenceType::CUSTOM_WEEKLY && ! $isPrivateStudent)
                            @continue
                        @endif
                        <option value="{{ $recurrenceType->value }}" @selected($currentRecurrenceType === $recurrenceType->value)>
                            {{ $recurrenceType->label() }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-foreground/60 mt-1">
                    Select how often this schedule should repeat. Choose "None" for a single occurrence.
                    @if ($isPrivateStudent)
                        For multiple days per week, choose "Custom Weekly (Select Days)".
                    @endif
                </p>
                <x-input-error :messages="$errors->get('recurrence_type')" class="mt-2" />
            </div>

            {{-- Day-of-week selector (custom weekly only, private students) --}}
            @if ($isPrivateStudent)
                <div id="weekly_days_container" class="hidden">
                    <x-input-label value="Days of the Week *" />
                    <p class="text-xs text-foreground/60 mt-1 mb-3" id="weekly_days_help">
                        Select which days each week the student will be tutored (e.g. Mon, Tue, Thu). Occurrences will be generated for every selected day between the start and end date.
                    </p>
                    <div class="flex flex-wrap gap-2" aria-describedby="weekly_days_help">
                        @php
                            $oldDays = old('weekly_days', []);
                        @endphp
                        @foreach (\App\Enums\WeekDay::cases() as $day)
                            <label class="weekly-day-label flex items-center gap-1.5 cursor-pointer rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-primary/10 hover:border-primary/50 select-none"
                                title="{{ $day->label() }}">
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
            @endif

            <div id="recurrence_end_date_container" class="{{ $currentRecurrenceType && $currentRecurrenceType !== 'none' ? '' : 'hidden' }}">
                <x-input-label for="recurrence_end_date" value="Recurrence End Date *" />
                <x-ui::input id="recurrence_end_date" name="recurrence_end_date" type="date"
                    class="mt-1 block w-full"
                    value="{{ $currentRecurrenceEndDate }}"
                    min="{{ old('schedule_date', $isEdit ? ($scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) : ($selectedDate?->format('Y-m-d') ?? now()->format('Y-m-d'))) }}" />
                <p class="text-xs text-foreground/60 mt-1">
                    The last occurrence will be created on or before this date. Must be after the schedule start date.
                </p>
                <x-input-error :messages="$errors->get('recurrence_end_date')" class="mt-2" />
            </div>

            <div id="occurrence_dates_container" class="hidden mt-4">
                <x-input-label value="Occurrence Dates *" />
                <p class="text-xs text-foreground/60 mt-1 mb-3">
                    Review the occurrence dates below. You can modify any date or remove unwanted
                    occurrences using the ✕ button (e.g., if a month has an extra week for a bi-weekly student).
                    Dates falling on weekends are highlighted in yellow.
                </p>
                <x-input-error :messages="$errors->get('occurrence_dates')" class="mt-2" />
                <x-input-error :messages="$errors->get('occurrence_dates.*')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section 3: Location & Meeting Details --}}
    <x-ui::card class="p-6 space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Location & Meeting Details</h2>
            <p class="text-sm text-foreground/60">
                {{ $isEdit ? 'Update online meeting details or location information for this session.' : 'Add online meeting details or location information for this session.' }}
            </p>
        </div>

        <div>
            <x-input-label for="location_details" value="Location/Meeting Details *" />
            <textarea name="location_details" id="location_details" rows="4"
                class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm"
                placeholder="Enter meeting link (e.g., Google Meet, Zoom), location address, or other meeting details..." required>{{ old('location_details', $isEdit ? $schedule->location_details : Auth::user()->therapistProfile?->default_meeting_location) }}</textarea>
            <p class="text-xs text-foreground/60 mt-1">
                Include meeting links for online sessions or address/location for in-person sessions.
            </p>
            <x-input-error :messages="$errors->get('location_details')" class="mt-2" />
        </div>
    </x-ui::card>

    {{-- Section 4: Notes --}}
    <x-ui::card class="p-6 space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Notes</h2>
            <p class="text-sm text-foreground/60">
                {{ $isEdit ? 'Update optional notes for this schedule.' : 'Add optional notes for this schedule.' }}
            </p>
        </div>

        <textarea name="notes" id="notes" rows="4"
            class="w-full border border-border rounded-lg px-3 py-2 text-sm" placeholder="Notes (optional)">{{ old('notes', $isEdit ? $schedule->notes : '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </x-ui::card>

    {{-- Section 5: Sub Coverage --}}
    @if (!$isEdit || ($isEdit && $subPanel && auth()->id() === $schedule?->therapist_id))
        <x-ui::card class="p-6 space-y-4" id="sub_request_card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Substitute Coverage</h2>
                    <p class="text-sm text-foreground/60">
                        @if ($isEdit && $subPanel && $subPanel['is_accepted'])
                            Substitute therapist confirmed for this session.
                        @else
                            Request a substitute therapist to cover this session if you are unavailable.
                        @endif
                    </p>
                </div>

                @if ($isEdit && $subPanel)
                    <div class="shrink-0 text-right">
                        @if ($subPanel['is_open'])
                            @php
                                $openLabel = 'Open'.($subPanel['status_summary'] ? ' · '.$subPanel['status_summary'] : '');
                                $metaParts = array_filter([
                                    $subPanel['requested_ago'] ? 'Requested '.$subPanel['requested_ago'] : null,
                                    $subPanel['session_in'] ? ucfirst($subPanel['session_in']) : null,
                                ]);
                            @endphp
                            <x-ui::badge variant="warning">{{ $openLabel }}</x-ui::badge>
                            @if ($metaParts)
                                <p class="mt-1 text-xs text-foreground/60">{{ implode(' · ', $metaParts) }}</p>
                            @endif
                        @elseif ($subPanel['is_accepted'])
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-success/20 bg-success/10 px-3 py-1 text-xs font-medium text-success">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                                Accepted
                            </span>
                        @elseif ($subPanel['is_cancelled'])
                            <x-ui::badge variant="muted">Cancelled</x-ui::badge>
                        @endif
                    </div>
                @endif
            </div>

            @if (!$isEdit)
                {{-- ── Create mode: checkbox toggle ── --}}
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="request_sub" id="request_sub" value="1"
                        class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        @checked(old('request_sub')) />
                    <span class="text-sm font-medium text-foreground">Request a substitute therapist for this session</span>
                </label>

                <div id="sub_reason_container" class="{{ old('request_sub') ? '' : 'hidden' }} space-y-4">
                    @include('therapist.schedule._sub_coverage_fields', [
                        'reasonFieldName'    => 'sub_reason',
                        'reasonFieldId'      => 'sub_reason',
                        'reasonValue'        => old('sub_reason'),
                        'pickerRootId'       => 'sub_invitee_picker',
                        'pickerTriggerId'    => 'sub_picker_trigger',
                        'pickerDropdownId'   => 'sub_picker_dropdown',
                        'pickerSearchId'     => 'sub_picker_search',
                        'pickerListId'       => 'sub_picker_list',
                        'pickerPlaceholderId'=> 'sub_picker_placeholder',
                        'hiddenInputsId'     => 'sub_invitee_inputs',
                        'eligibleSubsUrl'    => route('therapist.sub-requests.eligible-subs'),
                        'reasonErrors'       => $errors->get('sub_reason'),
                        'inviteeErrors'      => $errors->get('sub_invitee_ids'),
                        'inviteeStarErrors'  => $errors->get('sub_invitee_ids.*'),
                    ])
                </div>

                <x-input-error :messages="$errors->get('sub_request')" class="mt-2" />

            @elseif ($subPanel['is_accepted'])
                <div class="rounded-lg border border-success/30 bg-success/5 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-success/30 bg-success/10 text-sm font-semibold text-success">
                            {{ $subPanel['accepted_by_initials'] ?? '—' }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-sm font-semibold text-foreground">{{ $subPanel['accepted_by_name'] ?? '—' }}</span>
                                <span class="text-sm text-foreground/60">Covering Therapist</span>
                            </div>
                            @if ($subPanel['accepted_at'])
                                <p class="mt-0.5 flex items-center gap-1.5 text-sm text-foreground/60">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <circle cx="10" cy="10" r="7.25" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6v4l2.5 2.5" />
                                    </svg>
                                    Accepted {{ $subPanel['accepted_at'] }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif ($subPanel['is_cancelled'])
                {{-- ── Edit / Cancelled ── --}}
                <p class="text-sm text-foreground/60">This sub request was cancelled.</p>

            @elseif ($subPanel['is_open'])
                {{-- ── Edit / Open request ── --}}
                @if ($subPanel['reason'])
                    <div>
                        <p class="text-xs font-medium text-foreground/70 mb-1">Reason</p>
                        <p class="text-sm text-foreground whitespace-pre-line">{{ $subPanel['reason'] }}</p>
                    </div>
                @endif

                @if ($subPanel['invitee_rows']->isNotEmpty())
                    <div class="space-y-2">
                        <p class="text-xs font-medium text-foreground/70 uppercase tracking-wider">Invitees</p>
                        @foreach ($subPanel['invitee_rows'] as $row)
                            <div class="flex items-center justify-between rounded-lg border border-border px-4 py-2 {{ $row['is_muted'] ? 'bg-muted/30' : '' }}">
                                <span class="text-sm {{ $row['is_muted'] ? 'text-foreground/50 line-through' : 'text-foreground' }}">{{ $row['name'] }}</span>
                                <x-ui::badge :variant="$row['status_variant']">{{ $row['status_label'] }}</x-ui::badge>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Manage invitees --}}
                <div class="space-y-3">
                    <div>
                        <p class="text-sm font-medium text-foreground">Manage Invitees</p>
                        <p class="text-xs text-foreground/60">Add or remove therapists. Declined therapists can be re-invited. Changes save when you click Update Schedule.</p>
                    </div>

                    <div id="coverage_invitee_picker"
                        data-eligible-subs-url="{{ $subPanel['eligible_subs_url'] }}"
                        data-input-name="sub_invitee_ids[]"
                        data-mode="edit"
                        class="relative">
                        <div id="coverage_picker_trigger"
                            class="min-h-[2.5rem] w-full flex flex-wrap gap-1.5 items-center border border-border rounded-lg px-3 py-2 bg-background cursor-pointer focus-within:ring-2 focus-within:ring-primary/30"
                            tabindex="0" role="combobox" aria-expanded="false" aria-haspopup="listbox">
                            <span class="text-sm text-foreground/40 picker-placeholder" id="coverage_picker_placeholder">Loading eligible therapists…</span>
                        </div>
                        <div id="coverage_picker_dropdown"
                            class="hidden absolute z-20 mt-1 w-full bg-background border border-border rounded-lg shadow-lg max-h-56 overflow-y-auto"
                            role="listbox">
                            <div class="p-2 border-b border-border">
                                <input type="text" id="coverage_picker_search"
                                    class="w-full text-sm px-2 py-1.5 rounded border border-border bg-background focus:outline-none focus:ring-1 focus:ring-primary/30"
                                    placeholder="Search therapists…" autocomplete="off" />
                            </div>
                            <div id="coverage_picker_list" class="p-1"></div>
                        </div>
                    </div>

                    <div id="coverage_invitee_inputs"></div>
                </div>

            @else
                {{-- ── Edit / No request yet — checkbox UI; submits via outer Update Schedule ── --}}
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="request_sub" id="edit_request_sub" value="1"
                        class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        @checked(old('request_sub')) />
                    <span class="text-sm font-medium text-foreground">Request a substitute therapist for this session</span>
                </label>

                <div id="sub_reason_container" class="{{ old('request_sub') ? '' : 'hidden' }} space-y-4">
                    @include('therapist.schedule._sub_coverage_fields', [
                        'reasonFieldName'    => 'sub_reason',
                        'reasonFieldId'      => 'edit_sub_reason',
                        'reasonValue'        => old('sub_reason'),
                        'pickerRootId'       => 'sub_invitee_picker',
                        'pickerTriggerId'    => 'sub_picker_trigger',
                        'pickerDropdownId'   => 'sub_picker_dropdown',
                        'pickerSearchId'     => 'sub_picker_search',
                        'pickerListId'       => 'sub_picker_list',
                        'pickerPlaceholderId'=> 'sub_picker_placeholder',
                        'hiddenInputsId'     => 'sub_invitee_inputs',
                        'eligibleSubsUrl'    => $subPanel['eligible_subs_url'],
                        'reasonErrors'       => $errors->get('sub_reason'),
                        'inviteeErrors'      => $errors->get('sub_invitee_ids'),
                        'inviteeStarErrors'  => $errors->get('sub_invitee_ids.*'),
                    ])
                </div>
            @endif
        </x-ui::card>
    @endif

    <div class="flex justify-end gap-3">
        @if ($isEdit && $subPanel && $subPanel['is_open'])
            <button type="button"
                data-cancel-url="{{ $subPanel['cancel_url'] }}"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-danger/30 bg-background text-danger hover:bg-danger/10 transition-colors">
                Withdraw Request
            </button>
        @endif
        <a
            href="{{ route('therapist.schedule-calendar.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Schedule' : 'Create Schedule' }}
        </x-ui::button>
    </div>
</form>
