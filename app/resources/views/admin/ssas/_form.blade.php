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
                <select id="student_id" name="student_id"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    required>
                    <option value="">Select a student</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(old('student_id', $ssa->student_id ?? '') == $student->id)>
                            {{ $student->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="primary_service_id" value="Primary Service *" />
                <select id="primary_service_id" name="primary_service_id"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm {{ $isEdit ? 'bg-gray-100 cursor-not-allowed' : '' }}"
                    required {{ $isEdit ? 'disabled' : '' }}>
                    <option value="">Select a service</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('primary_service_id', $ssa->primary_service_id ?? '') == $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
                @if ($isEdit)
                    <input type="hidden" name="primary_service_id" value="{{ $ssa->primary_service_id }}">
                @endif
                <x-input-error :messages="$errors->get('primary_service_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="additional_service_ids" value="Additional Services (Indirect)" />
                <p class="mt-1 text-xs text-foreground/60">Select one or more indirect services like IEP meetings or
                    progress reports.</p>
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
                <select id="additional_service_ids" name="additional_service_ids[]" multiple data-select-box
                    data-placeholder="Select indirect services"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    @foreach ($indirectServices as $service)
                        <option value="{{ $service->id }}" @selected(in_array($service->id, $selectedAdditionalServices, true))>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$additionalServiceErrors" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="start_date" value="Start Date *" />
                    <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full"
                        value="{{ old('start_date', isset($ssa) ? $ssa->start_date->format('Y-m-d') : '') }}"
                        required />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="end_date" value="End Date *" />
                    <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full"
                        value="{{ old('end_date', isset($ssa) ? $ssa->end_date->format('Y-m-d') : '') }}" required />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: Scheduling Parameters --}}
    <x-ui::card class="p-6 space-y-6">
        <h3 class="text-lg font-semibold text-foreground">Scheduling Parameters</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="frequency-fields">
            <div>
                <x-input-label for="minutes_per_session" value="Minutes per Session *" />
                <p class="mt-1 text-xs text-foreground/60">Select in 5-minute increments</p>
                <select id="minutes_per_session" name="minutes_per_session"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    required>
                    <option value="">Select minutes</option>
                    @for ($i = 5; $i <= 180; $i += 5)
                        <option value="{{ $i }}" @selected(old('minutes_per_session', $ssa->minutes_per_session ?? '') == $i)>
                            {{ $i }} minutes
                        </option>
                    @endfor
                </select>
                <x-input-error :messages="$errors->get('minutes_per_session')" class="mt-2" />
            </div>

            <div id="frequency-field">
                <x-input-label for="frequency" value="Frequency *" />
                <p class="mt-1 text-xs text-foreground/60">How often sessions occur</p>
                <select id="frequency" name="frequency"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    <option value="">Select frequency</option>
                    @foreach ($frequencies as $frequency)
                        <option value="{{ $frequency->value }}" @selected(old('frequency', isset($ssa) && $ssa->frequency ? $ssa->frequency->value : '') == $frequency->value)>
                            {{ $frequency->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
            </div>

            <div id="sessions-per-frequency-field">
                <x-input-label for="sessions_per_frequency" value="Sessions per Frequency *" />
                <p class="mt-1 text-xs text-foreground/60">Number of sessions per frequency period</p>
                <x-text-input id="sessions_per_frequency" name="sessions_per_frequency" type="number" min="1"
                    max="100" class="mt-1 block w-full"
                    value="{{ old('sessions_per_frequency', isset($ssa) ? $ssa->sessions_per_frequency : '') }}" />
                <x-input-error :messages="$errors->get('sessions_per_frequency')" class="mt-2" />
            </div>

            <div id="calculated-minutes-field">
                <x-input-label for="calculated_minutes" value="Calculated minutes after Sessions per Frequency *" />
                <p class="mt-1 text-xs text-foreground/60">Auto-calculated based on sessions per frequency</p>
                <x-text-input id="calculated_minutes" name="calculated_minutes" type="number" min="0"
                    class="mt-1 block w-full"
                    value="{{ old('calculated_minutes', isset($ssa) ? $ssa->calculated_minutes : '') }}" />
                <x-input-error :messages="$errors->get('calculated_minutes')" class="mt-2" />
            </div>

            <div id="adjusted-minutes-field">
                <x-input-label for="adjusted_minutes" value="Adjusted minutes" />
                <p class="mt-1 text-xs text-foreground/60">Optional adjustment to calculated minutes</p>
                <x-text-input id="adjusted_minutes" name="adjusted_minutes" type="number" min="0"
                    class="mt-1 block w-full"
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
                <x-text-input id="tho_minutes" name="tho_minutes" type="number" min="0"
                    class="mt-1 block w-full" value="{{ old('tho_minutes', isset($ssa) ? $ssa->tho_minutes : '') }}"
                    required />
                <x-input-error :messages="$errors->get('tho_minutes')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="adjustment_notes" value="Adjustment Notes" />
            <p class="mt-1 text-xs text-foreground/60">Optional notes about any adjustments made</p>
            <textarea id="adjustment_notes" name="adjustment_notes" rows="4"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('adjustment_notes', isset($ssa) ? $ssa->adjustment_notes : '') }}</textarea>
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
    </x-ui::card>

    {{-- Section C: Assignment --}}
    <x-ui::card class="p-6 space-y-6">
        <h3 class="text-lg font-semibold text-foreground">Assignment</h3>

        <div>
            <x-input-label for="assigned_therapist_id" value="Assigned Therapist" />
            <p class="mt-1 text-xs text-foreground/60">Optional - can be assigned later. SSA will be in Pending status
                until assigned.</p>
            <select id="assigned_therapist_id" name="assigned_therapist_id"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                <option value="">Unassigned</option>
                @foreach ($therapists as $therapist)
                    <option value="{{ $therapist->id }}" @selected(old('assigned_therapist_id', isset($ssa) ? $ssa->assigned_therapist_id : '') == $therapist->id)>
                        {{ $therapist->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('assigned_therapist_id')" class="mt-2" />
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.ssas.index') }}"
            class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
            Cancel
        </a>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
            {{ $isEdit ? 'Update SSA' : 'Create SSA' }}
        </button>
    </div>
</form>
