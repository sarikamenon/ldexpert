{{-- Entity Billing Configuration Tab --}}
{{-- Required vars: $entityType (school|therapist), $entityId --}}
@php
    $configUrl = route('admin.billing.entity-config.show', ['entity_type' => $entityType, 'entity_id' => $entityId]);
    $saveUrl = route('admin.billing.entity-config.store');
    $destroyUrl = route('admin.billing.entity-config.destroy', ['entity_type' => $entityType, 'entity_id' => $entityId]);
@endphp

<div id="entityBillingTab"
     data-config-url="{{ $configUrl }}"
     data-save-url="{{ $saveUrl }}"
     data-destroy-url="{{ $destroyUrl }}"
     data-entity-type="{{ $entityType }}"
     data-entity-id="{{ $entityId }}"
     data-csrf="{{ csrf_token() }}">

    {{-- Status Banner --}}
    <div id="billingStatusBanner" class="mb-6 rounded-lg border p-4 hidden">
        <div class="flex items-center gap-3">
            <div id="bannerIcon"></div>
            <div>
                <p id="bannerTitle" class="text-sm font-medium"></p>
                <p id="bannerSubtitle" class="text-xs text-foreground/60"></p>
            </div>
        </div>
    </div>

    {{-- Billing Mode Card (School/Family entities only) --}}
    @if ($entityType === 'school')
        <x-ui::card class="p-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground mb-4">Billing Mode</h2>
            <p class="text-xs text-foreground/60 mb-4">Choose how this school or family is billed. Standard (postpaid) bills after sessions are delivered. Advance (prepaid) bills before sessions occur.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="billing-mode-card relative flex items-start gap-4 rounded-lg border-2 p-4 cursor-pointer transition-all hover:border-primary/50" data-mode="standard">
                    <input type="radio" name="billing_mode" value="standard" class="mt-1 text-primary focus:ring-ring" checked>
                    <div>
                        <p class="text-sm font-semibold text-foreground">Standard (Postpaid)</p>
                        <p class="text-xs text-foreground/60 mt-1">Invoice generated after services are delivered based on actual session logs.</p>
                    </div>
                </label>

                <label class="billing-mode-card relative flex items-start gap-4 rounded-lg border-2 p-4 cursor-pointer transition-all hover:border-primary/50" data-mode="advance">
                    <input type="radio" name="billing_mode" value="advance" class="mt-1 text-primary focus:ring-ring">
                    <div>
                        <p class="text-sm font-semibold text-foreground">Advance (Prepaid)</p>
                        <p class="text-xs text-foreground/60 mt-1">Invoice generated before the billing period based on scheduled sessions.</p>
                    </div>
                </label>
            </div>
        </x-ui::card>
    @else
        <input type="hidden" name="billing_mode" value="standard">
    @endif

    {{-- Schedule Configuration Card --}}
    <x-ui::card class="p-6 space-y-6 mb-6">
        <h2 class="text-lg font-semibold text-foreground">Schedule Configuration</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <x-input-label for="eb_frequency" value="Frequency *" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_frequency_help">How often to generate invoices/bills.</p>
                <x-ui::select id="eb_frequency" name="frequency" class="mt-1" required :searchable="false" aria-describedby="eb_frequency_help">
                    @foreach (\App\Enums\BillingFrequency::cases() as $freq)
                        <option value="{{ $freq->value }}">{{ $freq->label() }}</option>
                    @endforeach
                </x-ui::select>
            </div>

            <div>
                <x-input-label for="eb_generation_day_type" value="Generation Timing *" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_gen_type_help">When to generate after the billing period ends.</p>
                <x-ui::select id="eb_generation_day_type" name="generation_day_type" class="mt-1" required :searchable="false" aria-describedby="eb_gen_type_help">
                    @foreach (\App\Enums\GenerationDayType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-ui::select>
            </div>

            <div id="eb_dayOfWeekGroup">
                <x-input-label for="eb_generation_day_of_week" value="Day of Week" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_dow_help">Generate on this weekday after the grace period.</p>
                <x-ui::select id="eb_generation_day_of_week" name="generation_day_of_week" class="mt-1" :searchable="false" aria-describedby="eb_dow_help">
                    <option value="0">Sunday</option>
                    <option value="1">Monday</option>
                    <option value="2" selected>Tuesday</option>
                    <option value="3">Wednesday</option>
                    <option value="4">Thursday</option>
                    <option value="5">Friday</option>
                    <option value="6">Saturday</option>
                </x-ui::select>
            </div>

            <div id="eb_fixedDelayGroup" style="display: none;">
                <x-input-label for="eb_generation_delay_days" value="Delay Days" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_delay_help">Number of days after period end to generate. 0 means the next day.</p>
                <x-ui::input type="number" id="eb_generation_delay_days" name="generation_delay_days" class="mt-1 block w-full"
                    value="3" min="0" max="30" aria-describedby="eb_delay_help" />
            </div>

            <input type="hidden" id="eb_min_grace_days" name="min_grace_days" value="2">

            <div>
                <x-input-label for="eb_billing_start_date" value="Billing Start Date" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_start_date_help">The date from which invoicing begins. No invoices will be generated for periods before this date.</p>
                <x-ui::input type="date" id="eb_billing_start_date" name="billing_start_date" class="mt-1 block w-full"
                    aria-describedby="eb_start_date_help" />
            </div>

            <div>
                <x-input-label for="eb_payment_terms_days" value="Payment Terms (Days) *" />
                <p class="mt-1 text-xs text-foreground/60" id="eb_terms_help">Due date = generation date + this many days.</p>
                <x-ui::input type="number" id="eb_payment_terms_days" name="payment_terms_days" class="mt-1 block w-full" required
                    value="30" min="1" max="90" aria-describedby="eb_terms_help" />
            </div>
        </div>
    </x-ui::card>

    {{-- Automation Card --}}
    <x-ui::card class="p-6 space-y-6 mb-6">
        <h2 class="text-lg font-semibold text-foreground">Automation</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex items-start gap-3">
                <input type="hidden" name="auto_generate" value="0">
                <input type="checkbox" id="eb_auto_generate" name="auto_generate" value="1"
                    class="mt-1 rounded border-border text-primary focus:ring-ring" checked>
                <div>
                    <x-input-label for="eb_auto_generate" value="Auto-generate" />
                    <p class="text-xs text-foreground/60">Automatically create draft invoices/bills when due.</p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <input type="hidden" name="auto_send" value="0">
                <input type="checkbox" id="eb_auto_send" name="auto_send" value="1"
                    class="mt-1 rounded border-border text-primary focus:ring-ring">
                <div>
                    <x-input-label for="eb_auto_send" value="Auto-send" />
                    <p class="text-xs text-foreground/60">Automatically send invoices/bills without admin review.</p>
                </div>
            </div>
        </div>

        <div>
            <x-input-label for="eb_notes" value="Notes" />
            <p class="mt-1 text-xs text-foreground/60" id="eb_notes_help">Optional notes about this billing configuration.</p>
            <textarea id="eb_notes" name="notes" rows="3"
                class="mt-1 block w-full rounded-md border-border bg-background text-foreground shadow-sm focus:border-ring focus:ring-ring text-sm"
                aria-describedby="eb_notes_help"></textarea>
        </div>
    </x-ui::card>

    {{-- Action Buttons --}}
    <div class="flex justify-end gap-3">
        <button type="button" id="ebResetBtn" class="hidden inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-danger border border-danger rounded-lg hover:bg-danger/10 transition-colors focus:outline-none focus:ring-2 focus:ring-ring">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            Reset to Defaults
        </button>
        <x-ui::button type="button" id="ebSaveBtn">
            Save Configuration
        </x-ui::button>
    </div>
</div>
