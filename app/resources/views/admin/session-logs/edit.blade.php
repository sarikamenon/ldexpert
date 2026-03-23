<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-session-log-override.js'])
    </x-slot>

    <div class="py-8"
        data-duration-minutes="{{ (int) ($sessionLog->duration_minutes ?? 0) }}"
        id="admin-session-log-override-form-container">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Admin · Session Logs</p>
                <h1 class="text-2xl font-semibold text-foreground">Override Session Log Rates</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Adjust therapist and school billing amounts for this session.
                </p>
                <div class="mt-2 flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-medium text-foreground/70">Session outcome:</span>
                    <x-ui::badge variant="secondary">
                        {{ $sessionLog->outcome?->label() ?? '—' }}
                    </x-ui::badge>
                </div>
                @if ($sessionLog->outcome && $sessionLog->outcome->paysNoShowRate())
                    <p class="mt-2 text-xs text-foreground/60">
                        This session used no-show rates. You can override the effective rate and amount below.
                    </p>
                @endif
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
                <form method="POST" action="{{ route('admin.session-logs.update', $sessionLog) }}" class="space-y-6" id="admin-session-log-override-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_rate_override" value="1" />

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="therapist_rate_type">
                                Therapist Rate Type
                            </label>
                            <select name="therapist_rate_type" id="therapist_rate_type"
                                class="border border-border rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-primary focus:border-primary js-rate-sync-therapist-type">
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
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="therapist_rate_amount">
                                Therapist Rate Amount
                            </label>
                            <x-ui::input type="number" step="0.01" name="therapist_rate_amount" id="therapist_rate_amount"
                                value="{{ old('therapist_rate_amount', $sessionLog->therapist_rate_amount ?? '') }}"
                                class="js-rate-sync-therapist-rate" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="therapist_billable_amount">
                                Therapist Billable Amount
                            </label>
                            <x-ui::input type="number" step="0.01" name="therapist_billable_amount" id="therapist_billable_amount"
                                value="{{ old('therapist_billable_amount', $sessionLog->therapist_billable_amount ?? '') }}"
                                class="js-rate-sync-therapist-amount" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="school_rate_type">
                                School Rate Type
                            </label>
                            <select name="school_rate_type" id="school_rate_type"
                                class="border border-border rounded-lg px-3 py-2 text-sm w-full focus:ring-2 focus:ring-primary focus:border-primary js-rate-sync-school-type">
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
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="school_rate_amount">
                                School Rate Amount
                            </label>
                            <x-ui::input type="number" step="0.01" name="school_rate_amount" id="school_rate_amount"
                                value="{{ old('school_rate_amount', $sessionLog->school_rate_amount ?? '') }}"
                                class="js-rate-sync-school-rate" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground/70 mb-1" for="school_invoice_amount">
                                School Invoice Amount
                            </label>
                            <x-ui::input type="number" step="0.01" name="school_invoice_amount" id="school_invoice_amount"
                                value="{{ old('school_invoice_amount', $sessionLog->school_invoice_amount ?? '') }}"
                                class="js-rate-sync-school-amount" />
                        </div>
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

