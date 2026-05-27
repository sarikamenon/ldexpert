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
                        'no_show_rate' => $service->no_show_rate,
                        'no_show_rate_type' => $service->getRawOriginal('no_show_rate_type') ? $service->no_show_rate_type->value : null,
                    ],
                )
                ->toArray()
            : [['service_id' => null, 'rate' => null, 'rate_type' => \App\Enums\RateType::HOURLY->value, 'no_show_rate' => null, 'no_show_rate_type' => null]],
    );

    $startDateValue = old('start_date', $isEdit ? optional($contract->start_date)->toDateString() : null);
    $endDateValue = old('end_date', $isEdit ? optional($contract->end_date)->toDateString() : null);
    $notesValue = old('notes', $isEdit ? $contract->notes : null);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-6" enctype="multipart/form-data">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Contract Details</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Left column: Therapist, Start Date, End Date --}}
            <div class="space-y-4">
                <div>
                    <x-input-label value="Therapist" />
                    <p class="mt-1 text-xs text-foreground/60">Therapist for this contract</p>
                    @if ($isEdit)
                        <p class="mt-2 font-medium">
                            {{ $contract->therapist?->first_name }} {{ $contract->therapist?->last_name }}
                        </p>
                    @else
                        <select name="therapist_id"
                            class="mt-1 w-full border border-border rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary">
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
                    <x-input-label value="Start Date" />
                    <p class="mt-1 text-xs text-foreground/60">Contract start date</p>
                    <x-ui::input type="date" name="start_date" class="mt-1 w-full" value="{{ $startDateValue }}" />
                    @error('start_date')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-input-label value="End Date" />
                    <p class="mt-1 text-xs text-foreground/60">Contract end date</p>
                    <x-ui::input type="date" name="end_date" class="mt-1 w-full" value="{{ $endDateValue }}" />
                    @error('end_date')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Right column: Notes --}}
            <div class="flex flex-col">
                <x-input-label value="Notes" />
                <p class="mt-1 text-xs text-foreground/60">Additional notes about this contract</p>
                <textarea name="notes"
                    class="mt-1 w-full flex-1 min-h-[200px] border border-border rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary focus:border-primary">{{ $notesValue }}</textarea>
                @error('notes')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </x-ui::card>

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Contract Document</h3>

        <div>
            <x-input-label value="Upload Document" />
            <p class="mt-1 text-xs text-foreground/60" id="document_help">
                Upload a contract document. Accepted formats: PDF, DOC, DOCX, JPG, PNG. Max 10MB.
            </p>

            @if ($isEdit && $contract->document_path)
                <div id="existing-document-name" class="mt-2 flex items-center gap-2">
                    <span class="text-sm font-medium text-foreground">{{ $contract->document_name }}</span>
                    <x-ui::button type="button" variant="danger" size="sm" id="remove-existing-document"
                        title="Remove document">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </x-ui::button>
                    <input type="hidden" name="remove_document" id="remove_document_input" value="0">
                </div>
            @endif

            <div class="mt-1 inline-flex items-center gap-2">
                <x-ui::file-input name="document" id="document_input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                    class="!w-auto" aria-describedby="document_help" />
                <x-ui::button type="button" variant="danger" size="sm" id="remove-selected-document"
                    class="hidden" title="Remove selected file">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </x-ui::button>
            </div>

            @error('document')
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
                        <th class="py-2 px-3">No-Show Rate</th>
                        <th class="py-2 px-3">No-Show Rate Type</th>
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
                                <x-ui::input type="number" step="0.01" min="0"
                                    name="services[{{ $index }}][rate]" value="{{ $serviceRow['rate'] }}"
                                    class="w-full" />
                                @error("services.$index.rate")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3">
                                <select name="services[{{ $index }}][rate_type]"
                                    class="ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground"
                                    data-select-box data-width="100%" data-searchable="true" data-placeholder="Select rate type">
                                    <option value="">Select rate type</option>
                                    @foreach ($rateTypes as $rateType)
                                        <option value="{{ $rateType->value }}" @selected(($serviceRow['rate_type'] ?? 'H') === $rateType->value)>
                                            {{ $rateType->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("services.$index.rate_type")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3">
                                <x-ui::input type="number" step="0.01" min="0"
                                    name="services[{{ $index }}][no_show_rate]" value="{{ $serviceRow['no_show_rate'] }}"
                                    class="w-full" />
                                @error("services.$index.no_show_rate")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3">
                                <select name="services[{{ $index }}][no_show_rate_type]"
                                    class="ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground"
                                    data-select-box data-width="100%" data-searchable="true" data-placeholder="Select rate type">
                                    <option value="">Select rate type</option>
                                    @foreach ($rateTypes as $rateType)
                                        <option value="{{ $rateType->value }}" @selected(($serviceRow['no_show_rate_type'] ?? 'H') === $rateType->value)>
                                            {{ $rateType->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error("services.$index.no_show_rate_type")
                                    <p class="text-xs text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </td>
                            <td class="py-2 px-3 text-center">
                                <x-ui::button type="button" variant="danger" size="sm" class="remove-service-row"
                                    title="Remove service">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path
                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                        </path>
                                    </svg>
                                </x-ui::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.contracts.therapists.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Contract' : 'Create Contract' }}
        </x-ui::button>
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
            <x-ui::input type="number" step="0.01" min="0" name="services[__INDEX__][rate]"
                class="w-full" />
        </td>
        <td class="py-2 px-3">
            <select name="services[__INDEX__][rate_type]"
                class="ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground"
                data-select-box data-width="100%" data-searchable="true" data-placeholder="Select rate type">
                <option value="">Select rate type</option>
                @foreach ($rateTypes as $rateType)
                    <option value="{{ $rateType->value }}" @selected($rateType->value === 'H')>{{ $rateType->label() }}</option>
                @endforeach
            </select>
        </td>
        <td class="py-2 px-3">
            <x-ui::input type="number" step="0.01" min="0" name="services[__INDEX__][no_show_rate]"
                class="w-full" />
        </td>
        <td class="py-2 px-3">
            <select name="services[__INDEX__][no_show_rate_type]"
                class="ld-select block w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-foreground"
                data-select-box data-width="100%" data-searchable="true" data-placeholder="Select rate type">
                <option value="">Select rate type</option>
                @foreach ($rateTypes as $rateType)
                    <option value="{{ $rateType->value }}" @selected($rateType->value === 'H')>{{ $rateType->label() }}</option>
                @endforeach
            </select>
        </td>
        <td class="py-2 px-3 text-center">
            <x-ui::button type="button" variant="danger" size="sm" class="remove-service-row"
                title="Remove service">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </x-ui::button>
        </td>
    </tr>
</template>
