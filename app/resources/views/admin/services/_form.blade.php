@php
    $isEdit = isset($service);
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.services.update', $service) : route('admin.services.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Hidden field for delivery_mode with virtual as default --}}
    <input type="hidden" name="delivery_mode" value="{{ old('delivery_mode', $service->delivery_mode ?? 'virtual') }}">

    {{-- Basic Information Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
        <p class="text-sm text-foreground/60 mb-4">Core details about the service</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="name" value="Service Name *" />
                <p class="mt-1 text-xs text-foreground/60">Appears everywhere the service is referenced (SSA, schedules,
                    invoices).</p>
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name ?? '')"
                    required placeholder="Enter service name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <p class="mt-1 text-xs text-foreground/60">Give admins and therapists context—what outcomes or
                    documentation are expected?</p>
                <textarea id="description" name="description" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    placeholder="Describe the service and its expected outcomes">{{ old('description', $service->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    <input type="hidden" name="is_group_service" value="0">

    {{-- Service Configuration Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Service Configuration</h3>
        <p class="text-sm text-foreground/60 mb-4">Define how this service operates</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                <x-input-label value="Has Frequency?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Check if the service has a specific frequency
                    requirement (e.g., Daily, Weekly, Monthly).</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_frequency_service" value="0">
                    <input id="is_frequency_service" name="is_frequency_service" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                        @checked(old('is_frequency_service', $service->is_frequency_service ?? false))>
                    <label for="is_frequency_service" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Service has frequency requirement
                    </label>
                </div>
                <x-input-error :messages="$errors->get('is_frequency_service')" class="mt-2" />
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                <x-input-label value="Direct Service?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Check only if therapists record direct student minutes.
                    Leave unchecked for indirect/reporting work.</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_direct_service" value="0">
                    <input id="is_direct_service" name="is_direct_service" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                        @checked(old('is_direct_service', $service->is_direct_service ?? true))>
                    <label for="is_direct_service" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Mark as direct
                    </label>
                </div>
                <x-input-error :messages="$errors->get('is_direct_service')" class="mt-2" />
            </div>

            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                <x-input-label value="Include in THO minutes?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Approved session minutes for this service will count
                    toward the student’s SSA THO minutes (based on session outcome).</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="include_in_tho" value="0">
                    <input id="include_in_tho" name="include_in_tho" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                        @checked(old('include_in_tho', $service->include_in_tho ?? false))>
                    <label for="include_in_tho" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Count approved minutes toward SSA THO
                    </label>
                </div>
                <x-input-error :messages="$errors->get('include_in_tho')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Billing & Duration Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Billing & Duration</h3>
        <p class="text-sm text-foreground/60 mb-4">Configure billing and session duration limits</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                <x-input-label value="Billable?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Only billable services flow into invoicing and therapist
                    payout calculations.</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_billable" value="0">
                    <input id="is_billable" name="is_billable" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                        @checked(old('is_billable', $service->is_billable ?? true))>
                    <label for="is_billable" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Included in invoicing
                    </label>
                </div>
                <x-input-error :messages="$errors->get('is_billable')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="min_duration_minutes" value="Min Duration (minutes)" />
                <p class="mt-1 text-xs text-foreground/60">Optional safeguard to prevent logging sessions shorter than
                    policy allows.</p>
                <x-text-input id="min_duration_minutes" name="min_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('min_duration_minutes', $service->min_duration_minutes ?? null)" placeholder="e.g., 30" />
                <x-input-error :messages="$errors->get('min_duration_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="max_duration_minutes" value="Max Duration (minutes)" />
                <p class="mt-1 text-xs text-foreground/60">Stops therapists from logging more minutes than authorized
                    for a single session.</p>
                <x-text-input id="max_duration_minutes" name="max_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('max_duration_minutes', $service->max_duration_minutes ?? null)" placeholder="e.g., 120" />
                <x-input-error :messages="$errors->get('max_duration_minutes')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Form Actions --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.services.index') }}"
            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
            Cancel
        </a>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90">
            {{ $isEdit ? 'Update Service' : 'Create Service' }}
        </button>
    </div>
</form>
