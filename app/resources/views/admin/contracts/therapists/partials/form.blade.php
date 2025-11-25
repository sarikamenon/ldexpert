@php
    $isEdit = isset($contract);
    $serviceRows = old(
        'services',
        $isEdit
            ? $contract->services
                ->map(
                    fn($service) => [
                        'service_id' => $service->service_id,
                        'rate' => $service->rate,
                        'rate_type' => $service->rate_type->value ?? $service->rate_type,
                    ],
                )
                ->toArray()
            : [['service_id' => null, 'rate' => null, 'rate_type' => \App\Enums\RateType::HOURLY->value]],
    );

    $selectedStatus = old('status', $isEdit ? $contract->status->value : \App\Enums\ContractStatus::ACTIVE->value);
    $startDateValue = old('start_date', $isEdit ? optional($contract->start_date)->toDateString() : null);
    $endDateValue = old('end_date', $isEdit ? optional($contract->end_date)->toDateString() : null);
    $notesValue = old('notes', $isEdit ? $contract->notes : null);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Contract Details</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label value="Therapist" />
                @if ($isEdit)
                    <p class="mt-2 font-medium">
                        {{ $contract->therapist?->first_name }} {{ $contract->therapist?->last_name }}
                    </p>
                @else
                    <select name="therapist_id"
                        class="mt-2 w-full border border-border rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Therapist</option>
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}" @selected(old('therapist_id') == $therapist->id)>
                                {{ $therapist->first_name }} {{ $therapist->last_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('therapist_id')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <div>
                <x-input-label value="Status" />
                <select name="status"
                    class="mt-2 w-full border border-border rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('status')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label value="Start Date" />
                <x-text-input type="date" name="start_date" class="mt-2 w-full" value="{{ $startDateValue }}" />
                @error('start_date')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-input-label value="End Date" />
                <x-text-input type="date" name="end_date" class="mt-2 w-full" value="{{ $endDateValue }}" />
                @error('end_date')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <x-input-label value="Notes" />
            <textarea name="notes" rows="4"
                class="mt-2 w-full border border-border rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary">{{ $notesValue }}</textarea>
            @error('notes')
                <p class="text-sm text-danger mt-1">{{ $message }}</p>
            @enderror
        </div>
    </x-ui::card>

    <x-ui::card class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-foreground">Service Rates</h3>
            <button type="button" id="addTherapistServiceRow"
                class="inline-flex items-center px-3 py-2 text-sm font-medium bg-primary text-white rounded-lg hover:bg-primary/90">
                Add Service
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="therapistContractServicesTable">
                <thead>
                    <tr class="text-left text-foreground/70">
                        <th class="py-2 px-3">Service</th>
                        <th class="py-2 px-3">Rate</th>
                        <th class="py-2 px-3">Rate Type</th>
                        <th class="py-2 px-3 w-16"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($serviceRows as $index => $serviceRow)
                        <tr class="service-row" data-index="{{ $index }}">
                            <td class="py-2 px-3">
                                <select name="services[{{ $index }}][service_id]"
                                    class="w-full border border-border rounded-lg px-2 py-2">
                                    <option value="">Select Service</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" @selected($serviceRow['service_id'] == $service->id)>
                                            {{ $service->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("services.$index.service_id")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3">
                                <x-text-input type="number" step="0.01" min="0"
                                    name="services[{{ $index }}][rate]" value="{{ $serviceRow['rate'] }}"
                                    class="w-full" />
                                @error("services.$index.rate")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3">
                                <select name="services[{{ $index }}][rate_type]"
                                    class="w-full border border-border rounded-lg px-2 py-2">
                                    @foreach ($rateTypes as $rateType)
                                        <option value="{{ $rateType->value }}" @selected(($serviceRow['rate_type'] ?? null) === $rateType->value)>
                                            {{ $rateType->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("services.$index.rate_type")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3 text-right">
                                <button type="button" class="remove-service-row text-danger hover:text-danger/80">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.contracts.therapists.index') }}"
            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">Cancel</a>
        <button type="submit"
            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
            {{ $isEdit ? 'Update Contract' : 'Create Contract' }}
        </button>
    </div>
</form>

<template id="therapistServiceRowTemplate">
    <tr class="service-row" data-index="__INDEX__">
        <td class="py-2 px-3">
            <select name="services[__INDEX__][service_id]" class="w-full border border-border rounded-lg px-2 py-2">
                <option value="">Select Service</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
        </td>
        <td class="py-2 px-3">
            <x-text-input type="number" step="0.01" min="0" name="services[__INDEX__][rate]"
                class="w-full" />
        </td>
        <td class="py-2 px-3">
            <select name="services[__INDEX__][rate_type]" class="w-full border border-border rounded-lg px-2 py-2">
                @foreach ($rateTypes as $rateType)
                    <option value="{{ $rateType->value }}">{{ $rateType->label() }}</option>
                @endforeach
            </select>
        </td>
        <td class="py-2 px-3 text-right">
            <button type="button" class="remove-service-row text-danger hover:text-danger/80">
                Remove
            </button>
        </td>
    </tr>
</template>
