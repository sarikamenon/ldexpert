<x-admin.layouts.app>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Edit Billing Schedule</h1>
                <p class="text-sm text-foreground/60 mt-1">Update the configuration for this billing schedule.</p>
            </div>
            <a href="{{ route('admin.billing.schedules.index') }}"
                class="inline-flex items-center gap-2 text-sm text-foreground/60 hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Schedules
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.billing.schedules.update', $schedule) }}" id="editScheduleForm">
        @csrf
        @method('PUT')

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Schedule Configuration</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="schedule_type" value="Schedule Type *" />
                    <p class="mt-1 text-xs text-foreground/60" id="schedule_type_help">What type of billing document to generate.</p>
                    <x-ui::select id="schedule_type" name="schedule_type" class="mt-1" required :searchable="false" aria-describedby="schedule_type_help">
                        @foreach ($scheduleTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('schedule_type', $schedule->schedule_type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('schedule_type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="schedulable_id" value="Entity *" />
                    <p class="mt-1 text-xs text-foreground/60" id="entity_help">The school, student, or therapist to bill.</p>
                    <x-ui::input type="number" id="schedulable_id" name="schedulable_id" class="mt-1 block w-full" required
                        value="{{ old('schedulable_id', $schedule->schedulable_id) }}" placeholder="Entity ID" aria-describedby="entity_help" />
                    <input type="hidden" name="schedulable_type" id="schedulable_type" value="{{ old('schedulable_type', $schedule->schedulable_type) }}">
                    <x-input-error :messages="$errors->get('schedulable_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="billing_mode" value="Billing Mode *" />
                    <p class="mt-1 text-xs text-foreground/60" id="billing_mode_help">Standard bills after delivery. Advance bills before sessions occur.</p>
                    <x-ui::select id="billing_mode" name="billing_mode" class="mt-1" required :searchable="false" aria-describedby="billing_mode_help">
                        @foreach ($billingModes as $mode)
                            <option value="{{ $mode->value }}" @selected(old('billing_mode', $schedule->billing_mode->value) === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('billing_mode')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="frequency" value="Frequency *" />
                    <p class="mt-1 text-xs text-foreground/60" id="frequency_help">How often to generate invoices/bills.</p>
                    <x-ui::select id="frequency" name="frequency" class="mt-1" required :searchable="false" aria-describedby="frequency_help">
                        @foreach ($frequencies as $freq)
                            <option value="{{ $freq->value }}" @selected(old('frequency', $schedule->frequency->value) === $freq->value)>{{ $freq->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Generation Timing</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="generation_day_type" value="Generation Timing *" />
                    <p class="mt-1 text-xs text-foreground/60" id="gen_type_help">When to generate after the period ends.</p>
                    <x-ui::select id="generation_day_type" name="generation_day_type" class="mt-1" required :searchable="false" aria-describedby="gen_type_help">
                        @foreach ($generationDayTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('generation_day_type', $schedule->generation_day_type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('generation_day_type')" class="mt-2" />
                </div>

                <div id="dayOfWeekGroup">
                    <x-input-label for="generation_day_of_week" value="Day of Week" />
                    <p class="mt-1 text-xs text-foreground/60" id="dow_help">Generate on this weekday after the grace period.</p>
                    <x-ui::select id="generation_day_of_week" name="generation_day_of_week" class="mt-1" :searchable="false" aria-describedby="dow_help">
                        @php $dow = old('generation_day_of_week', (string) $schedule->generation_day_of_week); @endphp
                        <option value="0" @selected($dow === '0')>Sunday</option>
                        <option value="1" @selected($dow === '1')>Monday</option>
                        <option value="2" @selected($dow === '2')>Tuesday</option>
                        <option value="3" @selected($dow === '3')>Wednesday</option>
                        <option value="4" @selected($dow === '4')>Thursday</option>
                        <option value="5" @selected($dow === '5')>Friday</option>
                        <option value="6" @selected($dow === '6')>Saturday</option>
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('generation_day_of_week')" class="mt-2" />
                </div>

                <div id="fixedDelayGroup" style="display: none;">
                    <x-input-label for="generation_delay_days" value="Delay Days" />
                    <p class="mt-1 text-xs text-foreground/60" id="delay_help">Number of days after period end to generate.</p>
                    <x-ui::input type="number" id="generation_delay_days" name="generation_delay_days" class="mt-1 block w-full"
                        value="{{ old('generation_delay_days', $schedule->generation_delay_days ?? '3') }}" min="1" max="30" aria-describedby="delay_help" />
                    <x-input-error :messages="$errors->get('generation_delay_days')" class="mt-2" />
                </div>

                <input type="hidden" name="min_grace_days" value="{{ old('min_grace_days', $schedule->min_grace_days) }}">

                <div>
                    <x-input-label for="billing_start_date" value="Billing Start Date" />
                    <p class="mt-1 text-xs text-foreground/60" id="start_date_help">The date from which invoicing begins. Leave empty to start immediately.</p>
                    <x-ui::input type="date" id="billing_start_date" name="billing_start_date" class="mt-1 block w-full"
                        value="{{ old('billing_start_date', $schedule->billing_start_date?->toDateString()) }}" aria-describedby="start_date_help" />
                    <x-input-error :messages="$errors->get('billing_start_date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="payment_terms_days" value="Payment Terms (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="terms_help">Due date = generation date + this many days.</p>
                    <x-ui::input type="number" id="payment_terms_days" name="payment_terms_days" class="mt-1 block w-full" required
                        value="{{ old('payment_terms_days', $schedule->payment_terms_days) }}" min="1" max="90" aria-describedby="terms_help" />
                    <x-input-error :messages="$errors->get('payment_terms_days')" class="mt-2" />
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Automation</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start gap-3">
                    <input type="hidden" name="auto_generate" value="0">
                    <input type="checkbox" id="auto_generate" name="auto_generate" value="1"
                        class="mt-1 rounded border-border text-primary focus:ring-ring"
                        @checked(old('auto_generate', $schedule->auto_generate))>
                    <div>
                        <x-input-label for="auto_generate" value="Auto-generate" />
                        <p class="text-xs text-foreground/60">Automatically create draft invoices/bills when due.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <input type="hidden" name="auto_send" value="0">
                    <input type="checkbox" id="auto_send" name="auto_send" value="1"
                        class="mt-1 rounded border-border text-primary focus:ring-ring"
                        @checked(old('auto_send', $schedule->auto_send))>
                    <div>
                        <x-input-label for="auto_send" value="Auto-send" />
                        <p class="text-xs text-foreground/60">Automatically send invoices/bills without admin review.</p>
                    </div>
                </div>
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <p class="mt-1 text-xs text-foreground/60" id="notes_help">Optional notes about this billing schedule.</p>
                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-border bg-background text-foreground shadow-sm focus:border-ring focus:ring-ring text-sm"
                    aria-describedby="notes_help">{{ old('notes', $schedule->notes) }}</textarea>
                <x-input-error :messages="$errors->get('notes')" class="mt-2" />
            </div>
        </x-ui::card>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.billing.schedules.index') }}">
                <x-ui::button variant="outline" type="button">Cancel</x-ui::button>
            </a>
            <x-ui::button type="submit">Update Schedule</x-ui::button>
        </div>
    </form>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-billing-schedules-form.js'])
    </x-slot>
</x-admin.layouts.app>
