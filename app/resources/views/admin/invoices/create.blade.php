<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-create.js'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Invoice</h1>
                <p class="text-sm text-foreground/60 mt-1">Enter invoice details. You can add session logs in the next step.</p>
            </div>
            <a href="{{ route('admin.invoices.index') }}"
                class="inline-flex items-center px-4 py-2 border border-border text-foreground rounded-lg text-sm font-medium hover:bg-background/subtle hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring">
                Back to Invoices
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

    <form method="POST" action="{{ route('admin.invoices.store') }}" id="createInvoiceForm">
        @csrf

        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Invoice Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <x-input-label for="school_id" value="School *" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_id_help">School to invoice. Session logs can be added in the next step.</p>
                    <x-ui::select id="school_id" name="school_id" class="mt-1" required searchable placeholder="Select School" aria-describedby="school_id_help">
                        <option value="">Select School</option>
                        @foreach ($schools ?? [] as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="invoice_date" value="Invoice Date *" />
                    <p class="mt-1 text-xs text-foreground/60" id="invoice_date_help">Date when the invoice is issued.</p>
                    <x-ui::input type="date" id="invoice_date" name="invoice_date" class="mt-1 block w-full" required
                        value="{{ old('invoice_date', now()->format('Y-m-d')) }}" aria-describedby="invoice_date_help" />
                    <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="invoice_number" value="Invoice Number *" />
                    <p class="mt-1 text-xs text-foreground/60" id="invoice_number_help">Auto-generated number is shown. You can edit if needed.</p>
                    <x-ui::input type="text" id="invoice_number" name="invoice_number" class="mt-1 block w-full"
                        value="{{ old('invoice_number', $invoiceNumber ?? '') }}" aria-describedby="invoice_number_help" />
                    <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="billing_period_start" value="Billing Period Start *" />
                    <p class="mt-1 text-xs text-foreground/60" id="billing_period_start_help">Start date of the billing period covered by this invoice.</p>
                    <x-ui::input type="date" id="billing_period_start" name="billing_period_start" class="mt-1 block w-full" required
                        value="{{ old('billing_period_start', $defaultDateFrom ?? now()->subDays(30)->format('Y-m-d')) }}" aria-describedby="billing_period_start_help" />
                    <x-input-error :messages="$errors->get('billing_period_start')" class="mt-2" />
                </div>

                <div class="space-y-1">
                    <x-input-label for="billing_period_end" value="Billing Period End *" />
                    <p class="mt-1 text-xs text-foreground/60" id="billing_period_end_help">End date of the billing period covered by this invoice.</p>
                    <x-ui::input type="date" id="billing_period_end" name="billing_period_end" class="mt-1 block w-full" required
                        value="{{ old('billing_period_end', $defaultDateTo ?? now()->format('Y-m-d')) }}" aria-describedby="billing_period_end_help" />
                    <x-input-error :messages="$errors->get('billing_period_end')" class="mt-2" />
                </div>

                <div class="space-y-1 md:col-span-2">
                    <x-input-label for="notes" value="Notes (Optional)" />
                    <p class="mt-1 text-xs text-foreground/60" id="notes_help">Additional notes or comments for this invoice.</p>
                    <textarea id="notes" name="notes" rows="3" aria-describedby="notes_help"
                        class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:border-primary">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
            </div>
        </x-ui::card>

        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('admin.invoices.index') }}">
                <x-ui::button variant="secondary">
                    Cancel
                </x-ui::button>
            </a>
            <x-ui::button type="submit">
                Create draft
            </x-ui::button>
        </div>
    </form>
</x-admin.layouts.app>
