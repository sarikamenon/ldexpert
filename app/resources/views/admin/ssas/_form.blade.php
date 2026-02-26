@php
    $isEdit = isset($ssa);
@endphp

@if ($errors->has('overlap'))
    <x-ui::alert variant="danger" class="mb-4">{{ $errors->first('overlap') }}</x-ui::alert>
@endif

<form method="POST" action="{{ $isEdit ? route('admin.ssas.update', $ssa) : route('admin.ssas.store') }}"
    class="space-y-6" id="ssaForm">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Section A: Core Details --}}
    <x-ui::card class="p-6 space-y-6">
        <h3 class="text-lg font-semibold text-foreground">Core Details</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="student_id" value="Student *" />
                <p class="mt-1 text-xs text-foreground/60">Choose the student this SSA belongs to.</p>
                <x-ui::select id="student_id" name="student_id" class="mt-1" placeholder="Select a student" required>
                    <option value="">Select a student</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id', $ssa->student_id ?? '') == $student->id)>
                            {{ $student->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="primary_service_id" value="Primary Service *" />
                <p class="mt-1 text-xs text-foreground/60">Pick the main direct service that drives scheduling.</p>
                <x-ui::select id="primary_service_id" name="primary_service_id" class="mt-1"
                    placeholder="Select a service" required :disabled="$isEdit">
                    <option value="">Select a service</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('primary_service_id', $ssa->primary_service_id ?? '') == $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                @if ($isEdit)
                    <input type="hidden" name="primary_service_id" value="{{ $ssa->primary_service_id }}">
                @endif
                <x-input-error :messages="$errors->get('primary_service_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="additional_service_ids" value="Additional Services (Indirect)" />
                <p id="additional_service_ids_help" class="mt-1 mb-1 text-xs text-foreground/60">Select one or more
                    indirect
                    services like IEP meetings or progress reports.</p>
                @php
                    $selectedAdditionalServices = collect(
                        old('additional_service_ids', isset($ssa) ? $ssa->additionalServices->pluck('id')->all() : []),
                    )
                        ->map(fn($id) => (int) $id)
                        ->toArray();
                    $additionalServiceErrors = array_merge(
                        $errors->get('additional_service_ids') ?? [],
                        $errors->get('additional_service_ids.*') ?? [],
                    );
                @endphp
                <x-ui::select id="additional_service_ids" name="additional_service_ids[]" multiple searchable
                    placeholder="None" class="mt-1" aria-describedby="additional_service_ids_help">
                    @foreach ($indirectServices as $service)
                        <option value="{{ $service->id }}" @selected(in_array($service->id, $selectedAdditionalServices, true))>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$additionalServiceErrors" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="start_date" value="Start Date *" />
                    <p class="mt-1 text-xs text-foreground/60">Service period start date.</p>
                    <x-ui::input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                        value="{{ old('start_date', isset($ssa) ? $ssa->start_date->format('Y-m-d') : '') }}"
                        required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="end_date" value="End Date *" />
                    <p class="mt-1 text-xs text-foreground/60">Service period end date.</p>
                    <x-ui::input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                        value="{{ old('end_date', isset($ssa) ? $ssa->end_date->format('Y-m-d') : '') }}" required />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="assigned_therapist_id" value="Assigned Therapist" />
                <p class="mt-1 text-xs text-foreground/60">Optional - can be assigned later. SSA will be in Pending
                    status
                    until assigned.</p>
                <x-ui::select id="assigned_therapist_id" name="assigned_therapist_id" searchable class="mt-1"
                    placeholder="Unassigned">
                    <option value="">Unassigned</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}" @selected(old('assigned_therapist_id', isset($ssa) ? $ssa->assigned_therapist_id : '') == $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('assigned_therapist_id')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: Scheduling Parameters --}}
    <x-ui::card class="p-6 space-y-6">
        <h3 class="text-lg font-semibold text-foreground">Scheduling Parameters</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="frequency-fields">
            <div>
                <x-input-label for="minutes_per_session" value="Minutes per Session *" />
                <p id="minutes_per_session_help" class="mt-1 text-xs text-foreground/60">
                    Enter the minutes for each session (minimum {{ config('session_minutes.min') }}, maximum
                    {{ config('session_minutes.max') }}).
                </p>
                <x-ui::input id="minutes_per_session" name="minutes_per_session" type="number"
                    class="mt-1 block w-full" min="{{ config('session_minutes.min') }}"
                    max="{{ config('session_minutes.max') }}" step="1"
                    aria-describedby="minutes_per_session_help"
                    value="{{ old('minutes_per_session', $ssa->minutes_per_session ?? '') }}" required />
                <x-input-error :messages="$errors->get('minutes_per_session')" class="mt-2" />
            </div>

            <div id="frequency-field">
                <x-input-label for="frequency" value="Frequency *" />
                <p id="frequency_help" class="mt-1 text-xs text-foreground/60">How often sessions occur</p>
                <x-ui::select id="frequency" name="frequency" class="mt-1" placeholder="Select frequency"
                    aria-describedby="frequency_help">
                    <option value="">Select frequency</option>
                    @foreach ($frequencies as $frequency)
                        <option value="{{ $frequency->value }}" @selected(old('frequency', isset($ssa) && $ssa->frequency ? $ssa->frequency->value : '') == $frequency->value)>
                            {{ $frequency->label() }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
            </div>

            <div id="sessions-per-frequency-field">
                <x-input-label for="sessions_per_frequency" value="Sessions per Frequency *" />
                <p id="sessions_per_frequency_help" class="mt-1 text-xs text-foreground/60">Number of sessions per
                    frequency period</p>
                <x-ui::input id="sessions_per_frequency" name="sessions_per_frequency" type="number" min="1"
                    max="100" class="mt-1 block w-full" aria-describedby="sessions_per_frequency_help"
                    value="{{ old('sessions_per_frequency', isset($ssa) ? $ssa->sessions_per_frequency : '') }}" />
                <x-input-error :messages="$errors->get('sessions_per_frequency')" class="mt-2" />
            </div>

            <div id="calculated-minutes-field">
                <x-input-label for="calculated_minutes" value="Calculated minutes after Sessions per Frequency *" />
                <p id="calculated_minutes_help" class="mt-1 text-xs text-foreground/60">Auto-calculated based on
                    sessions per frequency</p>
                <x-ui::input id="calculated_minutes" name="calculated_minutes" type="number" min="0"
                    class="mt-1 block w-full" aria-describedby="calculated_minutes_help"
                    value="{{ old('calculated_minutes', isset($ssa) ? $ssa->calculated_minutes : '') }}" />
                <x-input-error :messages="$errors->get('calculated_minutes')" class="mt-2" />
            </div>

            <div id="adjusted-minutes-field">
                <x-input-label for="adjusted_minutes" value="Adjusted minutes" />
                <p id="adjusted_minutes_help" class="mt-1 text-xs text-foreground/60">Optional adjustment to
                    calculated
                    minutes</p>
                <x-ui::input id="adjusted_minutes" name="adjusted_minutes" type="number" class="mt-1 block w-full"
                    aria-describedby="adjusted_minutes_help"
                    value="{{ old('adjusted_minutes', isset($ssa) ? $ssa->adjusted_minutes : '') }}" />
                <x-input-error :messages="$errors->get('adjusted_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="tho_minutes" value="THO Minutes (Total Hours Own by therapist) *" />
                <p class="mt-1 text-xs text-foreground/60">
                    <span id="tho-calculation-hint">Auto-calculated: Minutes per Session × (Sessions per Frequency ×
                        Number
                        of Frequencies in Date Range)</span>
                </p>
                <x-ui::input id="tho_minutes" name="tho_minutes" type="number" min="0"
                    class="mt-1 block w-full" value="{{ old('tho_minutes', isset($ssa) ? $ssa->tho_minutes : '') }}"
                    aria-describedby="tho-calculation-hint" required />
                <x-input-error :messages="$errors->get('tho_minutes')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="adjustment_notes" value="Adjustment Notes" />
            <p id="adjustment_notes_help" class="mt-1 text-xs text-foreground/60">Optional notes about any adjustments
                made</p>
            <textarea id="adjustment_notes" name="adjustment_notes" rows="4"
                class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm"
                aria-describedby="adjustment_notes_help">{{ old('adjustment_notes', isset($ssa) ? $ssa->adjustment_notes : '') }}</textarea>
            <x-input-error :messages="$errors->get('adjustment_notes')" class="mt-2" />
        </div>

        {{-- Store service frequency support data for JavaScript --}}
        <script type="application/json" id="services-data">
            @json($services->mapWithKeys(fn($service) => [$service->id => $service->is_frequency_service]))
        </script>
        @if (isset($ssa) && $ssa->primaryService)
            <script type="application/json" id="current-service-data">
                @json(['id' => $ssa->primaryService->id, 'supports_frequency' => $ssa->primaryService->is_frequency_service])
            </script>
        @endif
        <script type="application/json" id="therapists-for-service-url">
            @json(route('admin.ssas.therapists-for-service'))
        </script>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.ssas.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update SSA' : 'Create SSA' }}
        </x-ui::button>
    </div>
</form>
