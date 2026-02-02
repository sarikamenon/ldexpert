@php
    $isEdit = isset($sessionLog);
@endphp

<form method="POST"
    action="{{ $isEdit ? route('therapist.session-logs.update', $sessionLog) : route('therapist.session-logs.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if (isset($schedule))
        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}" />
    @endif

    {{-- Session Details --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Session Details</h3>

        {{-- Row 1: Student + SSA (always read-only to keep aligned with SSA) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Student</label>
                <p class="mt-1 text-xs text-foreground/60">Student associated with this session log</p>
                <input type="hidden" name="student_id"
                    value="{{ old('student_id', $sessionLog->student_id ?? ($schedule->student_id ?? ($selectedSsa->student_id ?? ''))) }}" />
                <x-ui::input type="text" id="session-log-student-name" readonly :disabled="true"
                    value="{{ $sessionLog->student->name ?? ($schedule->student?->name ?? ($selectedSsa->student?->name ?? '')) }}"
                    class="mt-1 bg-gray-100" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">SSA</label>
                <p class="mt-1 text-xs text-foreground/60">Service Support Agreement for this session</p>
                <input type="hidden" name="ssa_id"
                    value="{{ old('ssa_id', $sessionLog->ssa_id ?? ($schedule->ssa_id ?? ($selectedSsa->id ?? ''))) }}" />
                <x-ui::select id="session-log-ssa" name="ssa_id" disabled class="mt-1 bg-gray-100">
                    <option value="">Select SSA</option>
                    @foreach ($ssas ?? [] as $ssa)
                        <option value="{{ $ssa->id }}" @selected(old('ssa_id', $sessionLog->ssa_id ?? ($schedule->ssa_id ?? ($selectedSsa->id ?? ''))) == $ssa->id)
                            data-student-id="{{ $ssa->student_id }}" data-student-name="{{ $ssa->student?->name }}"
                            data-start-date="{{ $ssa->start_date?->format('Y-m-d') }}"
                            data-end-date="{{ $ssa->end_date?->format('Y-m-d') }}">
                            {{ $ssa->student?->name }} (SSA #{{ $ssa->id }} - {{ $ssa->primaryService?->name }})
                        </option>
                    @endforeach
                </x-ui::select>
            </div>
        </div>

        {{-- Row 2: Service + Session Date --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Service</label>
                <p class="mt-1 text-xs text-foreground/60">Service provided during this session</p>
                @if (isset($schedule))
                    {{-- From schedule: service is read-only --}}
                    <input type="hidden" name="service_id"
                        value="{{ old('service_id', $sessionLog->service_id ?? ($schedule->service_id ?? '')) }}" />
                    <x-ui::input type="text" readonly :disabled="true"
                        value="{{ $schedule->service?->name ?? ($services->firstWhere('id', $schedule->service_id)->name ?? '') }}"
                        class="mt-1 bg-gray-100" />
                @else
                    {{-- Standalone: service selectable based on SSA --}}
                    <x-ui::select name="service_id" id="session-log-service" class="mt-1" required>
                        @if (isset($sessionLog) || isset($selectedSsa))
                            <option value="">Select service</option>
                            @foreach ($services ?? [] as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id', $sessionLog->service_id ?? ($selectedSsa->primary_service_id ?? '')) == $service->id)>
                                    {{ $service->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="">Select SSA first</option>
                        @endif
                    </x-ui::select>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Session Date</label>
                <p class="mt-1 text-xs text-foreground/60">Date when the session occurred</p>
                <x-ui::input type="date" name="session_date" id="session-log-date"
                    value="{{ old('session_date', isset($sessionLog) ? $sessionLog->session_date?->format('Y-m-d') : (isset($schedule) ? $schedule->schedule_date?->format('Y-m-d') : now()->format('Y-m-d'))) }}"
                    @if (isset($selectedSsa) && $selectedSsa?->start_date) min="{{ $selectedSsa->start_date->format('Y-m-d') }}" @endif
                    @if (isset($selectedSsa) && $selectedSsa?->end_date) max="{{ $selectedSsa->end_date->format('Y-m-d') }}" @endif
                    class="mt-1"
                    required />
            </div>
        </div>

        {{-- Row 3: Start Time + Duration + End Time --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Start Time</label>
                <p class="mt-1 text-xs text-foreground/60">Time when the session started</p>
                <x-ui::input type="time" name="start_time" id="session-log-start-time"
                    value="{{ old('start_time', isset($sessionLog) ? $sessionLog->start_time?->format('H:i') : (isset($schedule) ? $schedule->start_time?->format('H:i') : '')) }}"
                    class="mt-1"
                    required />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Duration (minutes)</label>
                <p class="mt-1 text-xs text-foreground/60">Session duration in minutes (minimum 5, increments of 5)</p>
                <x-ui::input type="number" name="duration_minutes" id="session-log-duration"
                    value="{{ old('duration_minutes', isset($sessionLog) ? $sessionLog->duration_minutes ?? '' : (isset($schedule) ? $schedule->durationMinutes() : '')) }}"
                    min="5" step="5"
                    class="mt-1"
                    required />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">End Time</label>
                <p class="mt-1 text-xs text-foreground/60">Automatically calculated based on start time and duration</p>
                <x-ui::input type="time" name="end_time" id="session-log-end-time" readonly :disabled="true"
                    value="{{ old('end_time', isset($sessionLog) ? $sessionLog->end_time?->format('H:i') : (isset($schedule) ? $schedule->end_time?->format('H:i') : '')) }}"
                    class="mt-1 bg-gray-100"
                    required />
            </div>
        </div>

        <div x-data="{ open: true }" class="space-y-4 mt-4">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-foreground/70">Notes & Outcome</h4>
                <button type="button"
                    class="text-xs text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-base px-2 py-1"
                    @click="open = !open" x-bind:aria-expanded="open.toString()">
                    <span x-show="!open">Show</span>
                    <span x-show="open">Hide</span>
                </button>
            </div>

            <div x-show="open" x-cloak class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-foreground">Notes</label>
                    <p class="mt-1 text-xs text-foreground/60" id="session-notes-help">
                        Session notes must be at least 50 characters. Describe what occurred during the session.
                    </p>
                    <textarea name="notes" rows="4"
                        class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm"
                        aria-describedby="session-notes-help" required>{{ old('notes', $sessionLog->notes ?? '') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-foreground">Session Outcome</label>
                    <p class="mt-1 text-xs text-foreground/60">Select the outcome of this session</p>
                    <x-ui::select name="outcome" class="mt-1">
                        @foreach ($sessionOutcomes ?? [] as $outcome)
                            <option value="{{ $outcome->value }}" @selected(old('outcome', $sessionLog->outcome?->value ?? 'service_delivered') === $outcome->value)>
                                {{ $outcome->label() }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    @error('outcome')
                        <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                    @enderror
                    {{-- Always billable flags are handled automatically; keep default as billable --}}
                    <input type="hidden" name="is_billable_therapist"
                        value="{{ old('is_billable_therapist', $sessionLog->is_billable_therapist ?? 1) }}">
                    <input type="hidden" name="is_billable_school" value="1">
                </div>
            </div>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <x-ui::loading-button variant="primary" x-data="{ loading: false }" x-on:click="loading = true">
            {{ $isEdit ? 'Update Session Log' : 'Create Session Log' }}
        </x-ui::loading-button>
    </div>
</form>
