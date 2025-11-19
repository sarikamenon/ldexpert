@php
    $isEdit = isset($service);
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.services.update', $service) : route('admin.services.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-ui::card class="p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="name" value="Service Name *" />
                <p class="mt-1 text-xs text-foreground/60">Appears everywhere the service is referenced (SSA, schedules,
                    invoices).</p>
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $service->name ?? '')"
                    required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="frequency" value="Frequency *" />
                <p class="mt-1 text-xs text-foreground/60">How often should this service occur? Select “Ad Hoc” if
                    cadence is flexible.</p>
                <select id="frequency" name="frequency"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    <option value="">Select frequency</option>
                    @foreach ($frequencies as $frequency)
                        <option value="{{ $frequency->value }}" @selected(old('frequency', $service->frequency?->value ?? '') === $frequency->value)>
                            {{ $frequency->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="delivery_mode" value="Delivery Mode *" />
            <p class="mt-1 text-xs text-foreground/60">Determines whether the service is offered virtually, in-person,
                or
                hybrid by default.</p>
            <select id="delivery_mode" name="delivery_mode"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                @foreach ($deliveryModes as $mode => $label)
                    <option value="{{ $mode }}" @selected(old('delivery_mode', $service->delivery_mode ?? (array_key_first($deliveryModes) ?? '')) === $mode)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('delivery_mode')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="description" value="Description" />
            <p class="mt-1 text-xs text-foreground/60">Give admins and therapists context—what outcomes or documentation
                are expected?</p>
            <textarea id="description" name="description" rows="4"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('description', $service->description ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="status" value="Status *" />
                <p class="mt-1 text-xs text-foreground/60">Inactive services stay reportable but will not be offered in
                    new SSAs or sessions.</p>
                <select id="status" name="status"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $service->status?->value ?? 'active') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label value="Direct Service?" />
                    <p class="mt-1 text-xs text-foreground/60">Check only if therapists record direct student minutes.
                        Leave unchecked for indirect/reporting work.</p>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="hidden" name="direct_service" value="0">
                        <input id="direct_service" name="direct_service" type="checkbox" value="1"
                            class="rounded border-gray-300 text-primary focus:ring-primary"
                            @checked(old('direct_service', $service->direct_service ?? true))>
                        <label for="direct_service" class="text-sm text-foreground/80">Mark as direct</label>
                    </div>
                    <x-input-error :messages="$errors->get('direct_service')" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Group Service?" />
                    <p class="mt-1 text-xs text-foreground/60">Enable when this service is normally delivered to
                        multiple students simultaneously.</p>
                    <div class="mt-2 flex items-center gap-2">
                        <input type="hidden" name="group_service" value="0">
                        <input id="group_service" name="group_service" type="checkbox" value="1"
                            class="rounded border-gray-300 text-primary focus:ring-primary"
                            @checked(old('group_service', $service->group_service ?? false))>
                        <label for="group_service" class="text-sm text-foreground/80">Supports group delivery</label>
                    </div>
                    <x-input-error :messages="$errors->get('group_service')" class="mt-2" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label value="Billable?" />
                <p class="mt-1 text-xs text-foreground/60">Only billable services flow into invoicing and therapist
                    payout calculations.</p>
                <div class="mt-2 flex items-center gap-2">
                    <input type="hidden" name="is_billable" value="0">
                    <input id="is_billable" name="is_billable" type="checkbox" value="1"
                        class="rounded border-gray-300 text-primary focus:ring-primary" @checked(old('is_billable', $service->is_billable ?? true))>
                    <label for="is_billable" class="text-sm text-foreground/80">Included in invoicing</label>
                </div>
                <x-input-error :messages="$errors->get('is_billable')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="min_duration_minutes" value="Min Duration (mins)" />
                <p class="mt-1 text-xs text-foreground/60">Optional safeguard to prevent logging sessions shorter than
                    policy allows.</p>
                <x-text-input id="min_duration_minutes" name="min_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('min_duration_minutes', $service->min_duration_minutes ?? null)" />
                <x-input-error :messages="$errors->get('min_duration_minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="max_duration_minutes" value="Max Duration (mins)" />
                <p class="mt-1 text-xs text-foreground/60">Stops therapists from logging more minutes than authorized
                    for a single session.</p>
                <x-text-input id="max_duration_minutes" name="max_duration_minutes" type="number" min="5"
                    max="1440" class="mt-1 block w-full" :value="old('max_duration_minutes', $service->max_duration_minutes ?? null)" />
                <x-input-error :messages="$errors->get('max_duration_minutes')" class="mt-2" />
            </div>
        </div>

    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.services.index') }}"
            class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
            Cancel
        </a>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
            {{ $isEdit ? 'Update Service' : 'Create Service' }}
        </button>
    </div>
</form>
