<x-admin.layouts.app>
    @vite(['resources/js/pages/admin-billing-settings.js'])
    <x-page-title title="Billing Settings" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    <form method="POST" action="{{ route('admin.billing.settings.update') }}">
        @csrf
        @method('PUT')

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Standard Billing Defaults</h2>
            <p class="text-sm text-foreground/60">These defaults are applied when creating new billing schedules for regular schools.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="default_frequency" value="Default Frequency *" />
                    <p class="mt-1 text-xs text-foreground/60" id="freq_help">Default billing frequency for new schedules.</p>
                    <x-ui::select id="default_frequency" name="default_frequency" class="mt-1" required :searchable="false" aria-describedby="freq_help">
                        @foreach (\App\Enums\BillingFrequency::cases() as $freq)
                            <option value="{{ $freq->value }}" @selected(old('default_frequency', $settings->default_frequency->value) === $freq->value)>{{ $freq->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('default_frequency')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="default_generation_day_type" value="Default Generation Type *" />
                    <p class="mt-1 text-xs text-foreground/60" id="gen_type_help">How generation timing is determined.</p>
                    <x-ui::select id="default_generation_day_type" name="default_generation_day_type" class="mt-1" required :searchable="false" aria-describedby="gen_type_help">
                        @foreach (\App\Enums\GenerationDayType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('default_generation_day_type', $settings->default_generation_day_type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('default_generation_day_type')" class="mt-2" />
                </div>

                <div id="dow_wrapper" style="{{ old('default_generation_day_type', $settings->default_generation_day_type->value) === 'fixed_delay' ? 'display:none' : '' }}">
                    <x-input-label for="default_generation_day_of_week" value="Default Day of Week *" />
                    <p class="mt-1 text-xs text-foreground/60" id="dow_help">Which weekday to generate on (when using Day of Week type).</p>
                    <x-ui::select id="default_generation_day_of_week" name="default_generation_day_of_week" class="mt-1" :searchable="false" aria-describedby="dow_help">
                        @php $dow = old('default_generation_day_of_week', (string) $settings->default_generation_day_of_week); @endphp
                        <option value="0" @selected($dow === '0')>Sunday</option>
                        <option value="1" @selected($dow === '1')>Monday</option>
                        <option value="2" @selected($dow === '2')>Tuesday</option>
                        <option value="3" @selected($dow === '3')>Wednesday</option>
                        <option value="4" @selected($dow === '4')>Thursday</option>
                        <option value="5" @selected($dow === '5')>Friday</option>
                        <option value="6" @selected($dow === '6')>Saturday</option>
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('default_generation_day_of_week')" class="mt-2" />
                </div>

                <div id="grace_wrapper" style="{{ old('default_generation_day_type', $settings->default_generation_day_type->value) === 'day_of_week' ? 'display:none' : '' }}">
                    <x-input-label for="default_min_grace_days" value="Default Grace Days *" />
                    <p class="mt-1 text-xs text-foreground/60" id="grace_help">Minimum days to wait after period ends before generating.</p>
                    <x-ui::input type="number" id="default_min_grace_days" name="default_min_grace_days" class="mt-1 block w-full"
                        value="{{ old('default_min_grace_days', $settings->default_min_grace_days) }}" min="0" max="14" aria-describedby="grace_help" />
                    <x-input-error :messages="$errors->get('default_min_grace_days')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="default_payment_terms_days" value="Default Payment Terms (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="terms_help">Due date = generation date + this many days.</p>
                    <x-ui::input type="number" id="default_payment_terms_days" name="default_payment_terms_days" class="mt-1 block w-full" required
                        value="{{ old('default_payment_terms_days', $settings->default_payment_terms_days) }}" min="1" max="90" aria-describedby="terms_help" />
                    <x-input-error :messages="$errors->get('default_payment_terms_days')" class="mt-2" />
                </div>

                <div class="flex items-start gap-3 md:col-span-2">
                    <div class="flex items-start gap-6">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="default_auto_generate" value="0">
                            <input type="checkbox" id="default_auto_generate" name="default_auto_generate" value="1"
                                class="mt-1 rounded border-border text-primary focus:ring-ring"
                                @checked(old('default_auto_generate', $settings->default_auto_generate))>
                            <div>
                                <x-input-label for="default_auto_generate" value="Auto-generate by default" />
                                <p class="text-xs text-foreground/60">New schedules will auto-generate drafts.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="default_auto_send" value="0">
                            <input type="checkbox" id="default_auto_send" name="default_auto_send" value="1"
                                class="mt-1 rounded border-border text-primary focus:ring-ring"
                                @checked(old('default_auto_send', $settings->default_auto_send))>
                            <div>
                                <x-input-label for="default_auto_send" value="Auto-send by default" />
                                <p class="text-xs text-foreground/60">New schedules will auto-send without review.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Advance Billing Defaults (Prepaid Schools)</h2>
            <p class="text-sm text-foreground/60">These defaults are applied to schools with the Private Student flag enabled. They can be overridden per school.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="advance_default_frequency" value="Frequency *" />
                    <p class="mt-1 text-xs text-foreground/60" id="adv_freq_help">Default billing frequency for advance schedules.</p>
                    <x-ui::select id="advance_default_frequency" name="advance_default_frequency" class="mt-1" required :searchable="false" aria-describedby="adv_freq_help">
                        @foreach (\App\Enums\BillingFrequency::cases() as $freq)
                            <option value="{{ $freq->value }}" @selected(old('advance_default_frequency', $settings->advance_default_frequency->value) === $freq->value)>{{ $freq->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('advance_default_frequency')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="advance_default_generation_day_type" value="Generation Type *" />
                    <p class="mt-1 text-xs text-foreground/60" id="adv_gen_type_help">How generation timing is determined for advance schedules.</p>
                    <x-ui::select id="advance_default_generation_day_type" name="advance_default_generation_day_type" class="mt-1" required :searchable="false" aria-describedby="adv_gen_type_help">
                        @foreach (\App\Enums\GenerationDayType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('advance_default_generation_day_type', $settings->advance_default_generation_day_type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('advance_default_generation_day_type')" class="mt-2" />
                </div>

                <div id="adv_dow_wrapper" style="{{ old('advance_default_generation_day_type', $settings->advance_default_generation_day_type->value) === 'fixed_delay' ? 'display:none' : '' }}">
                    <x-input-label for="advance_default_generation_day_of_week" value="Day of Week *" />
                    <p class="mt-1 text-xs text-foreground/60" id="adv_dow_help">Which weekday to generate on for advance schedules.</p>
                    <x-ui::select id="advance_default_generation_day_of_week" name="advance_default_generation_day_of_week" class="mt-1" :searchable="false" aria-describedby="adv_dow_help">
                        @php $advDow = old('advance_default_generation_day_of_week', (string) $settings->advance_default_generation_day_of_week); @endphp
                        <option value="0" @selected($advDow === '0')>Sunday</option>
                        <option value="1" @selected($advDow === '1')>Monday</option>
                        <option value="2" @selected($advDow === '2')>Tuesday</option>
                        <option value="3" @selected($advDow === '3')>Wednesday</option>
                        <option value="4" @selected($advDow === '4')>Thursday</option>
                        <option value="5" @selected($advDow === '5')>Friday</option>
                        <option value="6" @selected($advDow === '6')>Saturday</option>
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('advance_default_generation_day_of_week')" class="mt-2" />
                </div>

                <div id="adv_grace_wrapper" style="{{ old('advance_default_generation_day_type', $settings->advance_default_generation_day_type->value) === 'day_of_week' ? 'display:none' : '' }}">
                    <x-input-label for="advance_default_min_grace_days" value="Grace Days *" />
                    <p class="mt-1 text-xs text-foreground/60" id="adv_grace_help">Minimum days to wait after period ends before generating.</p>
                    <x-ui::input type="number" id="advance_default_min_grace_days" name="advance_default_min_grace_days" class="mt-1 block w-full"
                        value="{{ old('advance_default_min_grace_days', $settings->advance_default_min_grace_days) }}" min="0" max="14" aria-describedby="adv_grace_help" />
                    <x-input-error :messages="$errors->get('advance_default_min_grace_days')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="advance_default_payment_terms_days" value="Payment Terms (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="adv_terms_help">Due date = generation date + this many days.</p>
                    <x-ui::input type="number" id="advance_default_payment_terms_days" name="advance_default_payment_terms_days" class="mt-1 block w-full" required
                        value="{{ old('advance_default_payment_terms_days', $settings->advance_default_payment_terms_days) }}" min="1" max="90" aria-describedby="adv_terms_help" />
                    <x-input-error :messages="$errors->get('advance_default_payment_terms_days')" class="mt-2" />
                </div>

                <div class="flex items-start gap-3 md:col-span-2">
                    <div class="flex items-start gap-6">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="advance_default_auto_generate" value="0">
                            <input type="checkbox" id="advance_default_auto_generate" name="advance_default_auto_generate" value="1"
                                class="mt-1 rounded border-border text-primary focus:ring-ring"
                                @checked(old('advance_default_auto_generate', $settings->advance_default_auto_generate))>
                            <div>
                                <x-input-label for="advance_default_auto_generate" value="Auto-generate by default" />
                                <p class="text-xs text-foreground/60">Advance schedules will auto-generate drafts.</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <input type="hidden" name="advance_default_auto_send" value="0">
                            <input type="checkbox" id="advance_default_auto_send" name="advance_default_auto_send" value="1"
                                class="mt-1 rounded border-border text-primary focus:ring-ring"
                                @checked(old('advance_default_auto_send', $settings->advance_default_auto_send))>
                            <div>
                                <x-input-label for="advance_default_auto_send" value="Auto-send by default" />
                                <p class="text-xs text-foreground/60">Advance schedules will auto-send without review.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Payment Reminders</h2>
            <p class="text-sm text-foreground/60">Configure when and how payment reminders are sent.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="reminder_days_before_due" value="Reminder Before Due Date (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="before_help">Send a reminder this many days before the due date.</p>
                    <x-ui::input type="number" id="reminder_days_before_due" name="reminder_days_before_due" class="mt-1 block w-full" required
                        value="{{ old('reminder_days_before_due', $settings->reminder_days_before_due) }}" min="1" max="30" aria-describedby="before_help" />
                    <x-input-error :messages="$errors->get('reminder_days_before_due')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="reminder_days_after_due" value="Overdue Reminder After (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="after_help">Send first overdue reminder this many days after the due date.</p>
                    <x-ui::input type="number" id="reminder_days_after_due" name="reminder_days_after_due" class="mt-1 block w-full" required
                        value="{{ old('reminder_days_after_due', $settings->reminder_days_after_due) }}" min="1" max="30" aria-describedby="after_help" />
                    <x-input-error :messages="$errors->get('reminder_days_after_due')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="reminder_overdue_repeat_days" value="Repeat Overdue Every (Days) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="repeat_help">Send follow-up overdue reminders every N days.</p>
                    <x-ui::input type="number" id="reminder_overdue_repeat_days" name="reminder_overdue_repeat_days" class="mt-1 block w-full" required
                        value="{{ old('reminder_overdue_repeat_days', $settings->reminder_overdue_repeat_days) }}" min="1" max="30" aria-describedby="repeat_help" />
                    <x-input-error :messages="$errors->get('reminder_overdue_repeat_days')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="max_overdue_reminders" value="Maximum Overdue Reminders *" />
                    <p class="mt-1 text-xs text-foreground/60" id="max_help">Stop sending overdue reminders after this many. Set to 0 for no limit.</p>
                    <x-ui::input type="number" id="max_overdue_reminders" name="max_overdue_reminders" class="mt-1 block w-full" required
                        value="{{ old('max_overdue_reminders', $settings->max_overdue_reminders) }}" min="0" max="10" aria-describedby="max_help" />
                    <x-input-error :messages="$errors->get('max_overdue_reminders')" class="mt-2" />
                </div>
            </div>
        </x-ui::card>

        <div class="flex justify-end">
            <x-ui::button type="submit">Save Settings</x-ui::button>
        </div>
    </form>
</x-admin.layouts.app>
