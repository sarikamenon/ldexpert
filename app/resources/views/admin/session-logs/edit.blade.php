<x-admin.layouts.app>
    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Admin · Session Logs</p>
                <h1 class="text-2xl font-semibold text-foreground">Override Session Log Rates</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Adjust therapist and school billing amounts for this session.
                </p>
            </div>

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">
                    <p class="font-semibold mb-2">Please fix the highlighted errors and try again.</p>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-6">
                <form method="POST" action="{{ route('admin.session-logs.update', $sessionLog) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                Therapist Rate Type
                            </label>
                            <select name="therapist_rate_type"
                                class="border border-border rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="">Select</option>
                                <option value="H"
                                    @selected(old('therapist_rate_type', $sessionLog->therapist_rate_type?->value ?? '') === 'H')>
                                    Hourly
                                </option>
                                <option value="F"
                                    @selected(old('therapist_rate_type', $sessionLog->therapist_rate_type?->value ?? '') === 'F')>
                                    Flat
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                Therapist Rate Amount
                            </label>
                            <x-ui-input type="number" step="0.01" name="therapist_rate_amount"
                                value="{{ old('therapist_rate_amount', $sessionLog->therapist_rate_amount ?? '') }}" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                Therapist Billable Amount
                            </label>
                            <x-ui-input type="number" step="0.01" name="therapist_billable_amount"
                                value="{{ old('therapist_billable_amount', $sessionLog->therapist_billable_amount ?? '') }}" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                School Rate Type
                            </label>
                            <select name="school_rate_type"
                                class="border border-border rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-primary focus:border-primary">
                                <option value="">Select</option>
                                <option value="H"
                                    @selected(old('school_rate_type', $sessionLog->school_rate_type?->value ?? '') === 'H')>
                                    Hourly
                                </option>
                                <option value="F"
                                    @selected(old('school_rate_type', $sessionLog->school_rate_type?->value ?? '') === 'F')>
                                    Flat
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                School Rate Amount
                            </label>
                            <x-ui-input type="number" step="0.01" name="school_rate_amount"
                                value="{{ old('school_rate_amount', $sessionLog->school_rate_amount ?? '') }}" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1">
                                School Invoice Amount
                            </label>
                            <x-ui-input type="number" step="0.01" name="school_invoice_amount"
                                value="{{ old('school_invoice_amount', $sessionLog->school_invoice_amount ?? '') }}" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <x-ui-checkbox name="is_rate_override" value="1"
                            label="Override Rates"
                            @checked(old('is_rate_override', $sessionLog->is_rate_override ?? false)) />
                        <p class="text-xs text-foreground/60">
                            Check this if you want to manually override the calculated rates for this session.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground/70 mb-1">
                            Override Reason
                        </label>
                        <textarea name="override_reason" rows="3"
                            class="border border-border rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-primary focus:border-primary"
                            placeholder="Explain why these rates are being overridden">{{ old('override_reason', $sessionLog->override_reason ?? '') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 text-sm font-medium">
                            Save
                        </button>
                        <a href="{{ route('admin.session-logs.show', $sessionLog) }}"
                            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                            Cancel
                        </a>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>
</x-admin.layouts.app>

