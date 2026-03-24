@props([
    'schedule' => null,
    'ssa' => null,
    'selectedDate' => null,
    'serviceOptions' => collect(),
    'preselectedService' => null,
    'preselectedStudent' => null,
    'studentServiceMappings' => collect(),
    'isEdit' => false,
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
                            <span class="text-foreground/70 block mb-1">School:</span>
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
                        value="{{ old('schedule_date', $schedule->schedule_date?->format('Y-m-d')) }}" required />
                @else
                    <x-ui::input id="schedule_date" name="schedule_date" type="date" class="mt-1 block w-full"
                        value="{{ old('schedule_date', $selectedDate?->format('Y-m-d')) }}"
                        min="{{ now()->format('Y-m-d') }}" required />
                @endif
                <x-input-error :messages="$errors->get('schedule_date')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="start_time" value="Start Time *" />
                    <x-ui::input id="start_time" name="start_time" type="time" class="mt-1 block w-full"
                        value="{{ old('start_time', $isEdit ? $schedule->start_time?->format('H:i') : '09:00') }}"
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

    {{-- Section 2: Recurrence Options (only for create) --}}
    @if (!$isEdit)
        <x-ui::card class="p-6 space-y-6">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Recurrence Options</h2>
                <p class="text-sm text-foreground/60">
                    Create a recurring schedule that repeats at regular intervals.
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <x-input-label for="recurrence_type" value="Recurrence Type *" />
                    <select id="recurrence_type" name="recurrence_type" data-select-box class="mt-1 block w-full"
                        required>
                        <option value="">Select recurrence type</option>
                        @foreach (\App\Enums\RecurrenceType::cases() as $recurrenceType)
                            <option value="{{ $recurrenceType->value }}" @selected(old('recurrence_type', 'none') === $recurrenceType->value)>
                                {{ $recurrenceType->label() }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-foreground/60 mt-1">
                        Select how often this schedule should repeat. Choose "None" for a single occurrence.
                    </p>
                    <x-input-error :messages="$errors->get('recurrence_type')" class="mt-2" />
                </div>

                <div id="recurrence_end_date_container" class="hidden">
                    <x-input-label for="recurrence_end_date" value="Recurrence End Date *" />
                    <x-ui::input id="recurrence_end_date" name="recurrence_end_date" type="date"
                        class="mt-1 block w-full" value="{{ old('recurrence_end_date', '') }}"
                        min="{{ old('schedule_date', $selectedDate?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" />
                    <p class="text-xs text-foreground/60 mt-1">
                        The last occurrence will be created on or before this date. Must be after the schedule start
                        date.
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
    @endif

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

    <div class="flex justify-end gap-3">
        <a
            href="{{ $isEdit ? route('therapist.schedule.calendar', ['date' => $schedule->schedule_date?->format('Y-m-d')]) : route('therapist.schedule.calendar') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Schedule' : 'Create Schedule' }}
        </x-ui::button>
    </div>
</form>
