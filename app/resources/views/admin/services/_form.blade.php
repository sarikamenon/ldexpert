@php
    $isEdit = isset($service);
    $selectedColor = old('color', $service->color ?? null);
    $isDirectChecked = old('is_direct_service', $service->is_direct_service ?? true);
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
                <x-ui::input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name ?? '')"
                    required placeholder="Enter service name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="description" value="Description" />
                <p class="mt-1 text-xs text-foreground/60">Give admins and therapists context—what outcomes or
                    documentation are expected?</p>
                <textarea id="description" name="description" rows="3"
                    class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    placeholder="Describe the service and its expected outcomes">{{ old('description', $service->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
        </div>

        {{-- Color Picker --}}
        <div class="mt-4">
            <x-input-label for="color_hex_input" value="Service Color" />
            <p class="mt-1 text-xs text-foreground/60" id="color_help">
                Pick a color to visually identify this service on schedules and calendars.
            </p>

            {{-- The actual value submitted --}}
            <input type="hidden" id="color" name="color" value="{{ $selectedColor ?? '' }}">

            <div class="mt-2 flex items-center gap-3">
                {{-- Color swatch button — clicking opens the native picker --}}
                <button type="button" id="color_swatch_btn"
                    aria-label="Open color picker"
                    class="relative w-9 h-9 rounded-lg border-2 border-border shadow-sm flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 transition-colors {{ $selectedColor ? '' : 'bg-muted' }}"
                    style="{{ $selectedColor ? 'background-color:' . $selectedColor . ';' : '' }}">
                    <input type="color" id="color_picker"
                        aria-describedby="color_help"
                        tabindex="-1"
                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        value="{{ $selectedColor ?: '#3B82F6' }}">
                </button>

                {{-- Live preview chip --}}
                <span id="color_preview_chip"
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $selectedColor ? 'border-transparent text-white' : 'border-border text-foreground/40 bg-muted' }}"
                    style="{{ $selectedColor ? 'background-color:' . $selectedColor . ';' : '' }}">
                    <span id="color_preview_label">{{ $selectedColor ?: 'No color' }}</span>
                </span>

                <button type="button" id="color_clear"
                    class="text-xs text-foreground/50 hover:text-danger transition-colors {{ $selectedColor ? '' : 'hidden' }}">
                    ✕ Clear
                </button>
            </div>
            <x-input-error :messages="$errors->get('color')" class="mt-2" />
        </div>
    </x-ui::card>

    <input type="hidden" name="is_group_service" value="0">

    {{-- Service Configuration Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Service Configuration</h3>
        <p class="text-sm text-foreground/60 mb-4">Define how this service operates</p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="p-4 border border-border rounded-lg bg-muted">
                <x-input-label value="Has Frequency?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Check if the service has a specific frequency
                    requirement (e.g., Daily, Weekly, Monthly).</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_frequency_service" value="0">
                    <input id="is_frequency_service" name="is_frequency_service" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                        @checked(old('is_frequency_service', $service->is_frequency_service ?? true))>
                    <label for="is_frequency_service" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Service has frequency requirement
                    </label>
                </div>
                <x-input-error :messages="$errors->get('is_frequency_service')" class="mt-2" />
            </div>

            <div class="p-4 border border-border rounded-lg bg-muted">
                <x-input-label value="Direct Service?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Check only if therapists record direct student minutes.
                    Leave unchecked for indirect/reporting work.</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_direct_service" value="0">
                    <input id="is_direct_service" name="is_direct_service" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                        @checked($isDirectChecked)>
                    <label for="is_direct_service" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Mark as direct
                    </label>
                </div>
                <x-input-error :messages="$errors->get('is_direct_service')" class="mt-2" />
            </div>

            <div class="p-4 border border-border rounded-lg bg-muted">
                <x-input-label value="Include in THO hours?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Approved session hours for this service will count
                    toward the student's SSA THO hours (based on session outcome).</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="include_in_tho" value="0">
                    <input id="include_in_tho" name="include_in_tho" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                        @checked(old('include_in_tho', $service->include_in_tho ?? false))>
                    <label for="include_in_tho" class="text-sm font-medium text-foreground/80 cursor-pointer">
                        Count approved hours toward SSA THO
                    </label>
                </div>
                <x-input-error :messages="$errors->get('include_in_tho')" class="mt-2" />
            </div>
        </div>

        {{-- Send Email toggle — only visible for indirect services --}}
        <div id="send-email-section"
            class="mt-4 p-4 border border-border rounded-lg bg-muted {{ $isDirectChecked ? 'hidden' : '' }}">
            <x-input-label value="Send Email Notification?" />
            <p class="mt-1 text-xs text-foreground/60 mb-3" id="send_email_help">
                When enabled, email notifications are sent to therapists and students when a schedule for this service is created or updated.
            </p>
            <input type="hidden" name="send_email" value="0">
            <div class="flex items-center gap-2">
                <input id="send_email" name="send_email" type="checkbox" value="1"
                    aria-describedby="send_email_help"
                    class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                    @checked(old('send_email', $service->send_email ?? true))>
                <label for="send_email" class="text-sm font-medium text-foreground/80 cursor-pointer">
                    Send email on creation or update of schedule
                </label>
            </div>
            <x-input-error :messages="$errors->get('send_email')" class="mt-2" />
        </div>
    </x-ui::card>

    {{-- Billing & Duration Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Billing & Duration</h3>
        <p class="text-sm text-foreground/60 mb-4">Configure billing and session duration limits</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 border border-border rounded-lg bg-muted">
                <x-input-label value="Billable?" />
                <p class="mt-1 text-xs text-foreground/60 mb-3">Only billable services flow into invoicing and therapist
                    payout calculations.</p>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_billable" value="0">
                    <input id="is_billable" name="is_billable" type="checkbox" value="1"
                        class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
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
                <x-ui::input id="min_duration_minutes" name="min_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('min_duration_minutes', $service->min_duration_minutes ?? null)" placeholder="e.g., 30" />
                <x-input-error :messages="$errors->get('min_duration_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="max_duration_minutes" value="Max Duration (minutes)" />
                <p class="mt-1 text-xs text-foreground/60">Stops therapists from logging more minutes than authorized
                    for a single session.</p>
                <x-ui::input id="max_duration_minutes" name="max_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('max_duration_minutes', $service->max_duration_minutes ?? null)" placeholder="e.g., 120" />
                <x-input-error :messages="$errors->get('max_duration_minutes')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Form Actions --}}
    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.services.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Service' : 'Create Service' }}
        </x-ui::button>
    </div>
</form>
