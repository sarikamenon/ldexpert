<x-admin.layouts.app>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Therapist Bill</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Create a draft first, then add or remove sessions in the next step.
                </p>
            </div>
            <a href="{{ route('admin.billing.therapist-bills.index') }}"
                class="inline-flex items-center px-4 py-2 border border-border text-foreground rounded-lg text-sm font-medium hover:bg-background/subtle focus:outline-none focus:ring-2 focus:ring-ring">
                Back to Bills
            </a>
        </div>
    </div>

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui::alert>
    @endif

    <form method="POST" action="{{ route('admin.billing.therapist-bills.store') }}" id="createBillForm">
        @csrf

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Bill Details</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <x-input-label for="therapist_id" value="Therapist *" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_id_help">
                        Therapist to bill for this draft.
                    </p>
                    <x-ui::select id="therapist_id" name="therapist_id" class="mt-1 block w-full" required
                        searchable placeholder="Select Therapist" aria-describedby="therapist_id_help">
                        <option value="">Select Therapist</option>
                        @foreach ($therapists ?? [] as $therapist)
                            <option value="{{ $therapist->id }}"
                                @selected((int) old('therapist_id', $selectedTherapistId) === $therapist->id)>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('therapist_id')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="bill_date" value="Bill Date *" />
                    <p class="mt-1 text-xs text-foreground/60" id="bill_date_help">
                        Date when the bill is issued.
                    </p>
                    <x-ui::input type="date" id="bill_date" name="bill_date" class="mt-1 block w-full" required
                        value="{{ old('bill_date', now()->format('Y-m-d')) }}" aria-describedby="bill_date_help" />
                    <x-input-error :messages="$errors->get('bill_date')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="bill_number" value="Bill Number" />
                    <p class="mt-1 text-xs text-foreground/60" id="bill_number_help">
                        Auto-generated number is shown. You can edit if needed.
                    </p>
                    <x-ui::input type="text" id="bill_number" name="bill_number" class="mt-1 block w-full"
                        value="{{ old('bill_number', $billNumber ?? '') }}" aria-describedby="bill_number_help" />
                    <x-input-error :messages="$errors->get('bill_number')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="billing_period_start" value="Billing Period Start *" />
                    <p class="mt-1 text-xs text-foreground/60" id="billing_period_start_help">
                        Start date of the billing period covered by this bill.
                    </p>
                    <x-ui::input type="date" id="billing_period_start" name="billing_period_start" class="mt-1 block w-full"
                        required value="{{ old('billing_period_start', $defaultDateFrom ?? now()->subDays(30)->format('Y-m-d')) }}"
                        aria-describedby="billing_period_start_help" />
                    <x-input-error :messages="$errors->get('billing_period_start')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="billing_period_end" value="Billing Period End *" />
                    <p class="mt-1 text-xs text-foreground/60" id="billing_period_end_help">
                        End date of the billing period covered by this bill.
                    </p>
                    <x-ui::input type="date" id="billing_period_end" name="billing_period_end" class="mt-1 block w-full"
                        required value="{{ old('billing_period_end', $defaultDateTo ?? now()->format('Y-m-d')) }}"
                        aria-describedby="billing_period_end_help" />
                    <x-input-error :messages="$errors->get('billing_period_end')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="due_date" value="Due Date" />
                    <p class="mt-1 text-xs text-foreground/60" id="due_date_help">
                        Date when payment is due (default: 30 days after the bill date).
                    </p>
                    <x-ui::input type="date" id="due_date" name="due_date" class="mt-1 block w-full"
                        value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}" aria-describedby="due_date_help" />
                    <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                </div>

                <div class="space-y-1 md:col-span-2">
                    <x-input-label for="notes" value="Notes (Optional)" />
                    <p class="mt-1 text-xs text-foreground/60" id="notes_help">
                        Additional notes or comments for this bill.
                    </p>
                    <textarea id="notes" name="notes" rows="3" aria-describedby="notes_help"
                        class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-ring">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('admin.billing.therapist-bills.index') }}">
                    <x-ui::button variant="secondary">
                        Cancel
                    </x-ui::button>
                </a>
                <x-ui::button type="submit">
                    Create draft
                </x-ui::button>
            </div>
        </x-ui::card>
    </form>
</x-admin.layouts.app>
