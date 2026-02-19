<x-admin.layouts.app>
    @php
        $isInvoiceMode = $mode === 'invoice';
        $title = $isInvoiceMode ? 'Record Invoice Payment' : 'Record Therapist Payment';
        $subtitle = $isInvoiceMode
            ? 'Record a lump-sum payment received from a school. The amount will be allocated to the oldest unpaid invoices.'
            : 'Record a lump-sum payment made to a therapist. The amount will be allocated to the oldest unpaid bills.';
        $formAction = $isInvoiceMode
            ? route('admin.payments.invoices.store')
            : route('admin.payments.therapist-bills.store');
    @endphp

    <x-ui::page-header :title="$title" :subtitle="$subtitle">
    </x-ui::page-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 max-w-2xl">
        <form method="POST" action="{{ $formAction }}" class="space-y-6">
            @csrf

            <div>
                @if ($isInvoiceMode)
                    <x-input-label for="school_id" value="Payer (School) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_id_help">
                        Select the school that sent this payment. The amount will be applied to that school&#039;s oldest unpaid invoices.
                    </p>
                    <select id="school_id" name="school_id" aria-describedby="school_id_help"
                        class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">Select school...</option>
                        @foreach ($entities as $school)
                            <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>
                        @endforeach
                    </select>
                    @error('school_id')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                @else
                    <x-input-label for="therapist_id" value="Payee (Therapist) *" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_id_help">
                        Select the therapist receiving this payment. The amount will be applied to their oldest unpaid bills.
                    </p>
                    <select id="therapist_id" name="therapist_id" aria-describedby="therapist_id_help"
                        class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">Select therapist...</option>
                        @foreach ($entities as $therapist)
                            <option value="{{ $therapist->id }}" @selected(old('therapist_id') == $therapist->id)>{{ $therapist->name }}</option>
                        @endforeach
                    </select>
                    @error('therapist_id')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="paid_at" value="Payment Date *" />
                    <p class="mt-1 text-xs text-foreground/60" id="paid_at_help">
                        Enter the date the payment was received or made. Future dates are not allowed.
                    </p>
                    <input type="date" id="paid_at" name="paid_at"
                        value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                        aria-describedby="paid_at_help"
                        class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    @error('paid_at')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-input-label for="amount" value="Amount *" />
                    <p class="mt-1 text-xs text-foreground/60" id="amount_help">
                        Enter the total payment amount. It will be allocated automatically to the oldest unpaid items.
                    </p>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                        value="{{ old('amount') }}"
                        aria-describedby="amount_help"
                        class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    @error('amount')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <x-input-label for="method" value="Payment Method *" />
                <p class="mt-1 text-xs text-foreground/60" id="method_help">
                    Choose how the payment was made (for example, check, bank transfer, or direct deposit).
                </p>
                <select id="method" name="method" aria-describedby="method_help"
                    class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                    <option value="">Select method...</option>
                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                        <option value="{{ $method->value }}" @selected(old('method') == $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
                @error('method')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-input-label for="reference" value="Reference Number" />
                <p class="mt-1 text-xs text-foreground/60" id="reference_help">
                    Optional: add a check number, transaction ID, or other reference to help reconcile this payment.
                </p>
                <input type="text" id="reference" name="reference"
                    value="{{ old('reference') }}" aria-describedby="reference_help"
                    class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    placeholder="Check number, transaction ID, etc.">
                @error('reference')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-input-label for="notes" value="Notes" />
                <p class="mt-1 text-xs text-foreground/60" id="notes_help">
                    Optional: add any internal notes about this payment, such as which invoices or bills it is expected to cover.
                </p>
                <textarea id="notes" name="notes" rows="3" aria-describedby="notes_help"
                    class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ $isInvoiceMode ? route('admin.payments.invoices.index') : route('admin.payments.therapist-bills.index') }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle focus:outline-none focus:ring-2 focus:ring-ring">
                    Cancel
                </a>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring">
                    Record Payment
                </button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>

