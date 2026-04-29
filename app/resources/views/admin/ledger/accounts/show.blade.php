<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::show-header :title="$accountName" :subtitle="$accountType . ' Account Ledger'"
        :back-url="route('admin.ledger.accounts.index', ['type' => $type === 'school' ? 'schools' : 'therapists'])"
        back-label="Back to Accounts" />

    @php
        $balance = (float) ($stats['current_balance'] ?? 0);
    @endphp

    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        @if ($type === 'school')
            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Invoiced</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_invoiced'], 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['invoice_count'] }} invoice(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Paid</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_paid'], 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['payment_count'] }} payment(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Credit Notes</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_credit_notes'] ?? 0, 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['credit_note_count'] ?? 0 }} credit note(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Refunds</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_refunds'] ?? 0, 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['refund_count'] ?? 0 }} refund(s)
                </p>
            </x-ui::card>
        @else
            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Billed</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_billed'], 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['bill_count'] }} bill(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Paid</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_paid'], 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['payment_count'] }} payment(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Credit Notes</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_credit_notes'] ?? 0, 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['credit_note_count'] ?? 0 }} credit note(s)
                </p>
            </x-ui::card>

            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">Total Refunds</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($stats['total_refunds'] ?? 0, 2) }}
                </p>
                <p class="text-xs text-foreground/60 mt-1">
                    {{ $stats['refund_count'] ?? 0 }} refund(s)
                </p>
            </x-ui::card>
        @endif

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Outstanding</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['outstanding'], 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['outstanding'] > 0 ? 'Balance due' : 'Paid in full' }}
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Current Balance</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format(abs($balance), 2) }}
                <span class="text-sm font-normal">
                    {{ $balance > 0 ? 'DR' : 'CR' }}
                </span>
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['transaction_count'] ?? 0 }} transaction(s)
            </p>
        </x-ui::card>
    </div>

    {{-- Ledger entries --}}
    <x-ui::card class="p-6 space-y-4">
        <h5 class="text-sm font-semibold text-foreground">Transaction History</h5>

        @if (isset($datatableUrl))
            <div class="overflow-x-auto">
                <table id="ledgerTransactionsTable" class="w-full border-collapse ledger-transactions-table display" data-datatable-url="{{ $datatableUrl }}" data-filter-type="{{ $datatableFilterType ?? $type }}" data-filter-id="{{ $datatableFilterId ?? $id }}">
                        <thead class="bg-background/subtle">
                            <tr>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Type</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Notes</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
            </div>
        @elseif ($ledgerEntries->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse ledger-transactions-table">
                        <thead class="bg-background/subtle">
                            <tr>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Type</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Notes</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ledgerEntries as $entry)
                                <tr class="border-t border-border hover:bg-background/subtle">
                                    <td class="py-3 px-4 text-sm">
                                        <div>{{ $entry->created_at->format('M d, Y') }}</div>
                                        <div class="text-xs text-foreground/60">
                                            {{ $entry->created_at->format('h:i A') }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        <x-ui::badge :variant="match ($entry->transaction_type->value) {
                                            'invoice_generated', 'bill_generated' => 'primary',
                                            'payment_received' => 'success',
                                            'payment_made' => 'danger',
                                            default => 'secondary',
                                        }">
                                            {{ $entry->transaction_type->label() }}
                                        </x-ui::badge>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        @if ($entry->reference)
                                            @php
                                                $referenceType = $entry->reference_type;
                                            @endphp

                                            @if ($referenceType === 'App\\Models\\Invoice')
                                                @php
                                                    $invoiceId = is_object($entry->reference)
                                                        ? $entry->reference->id
                                                        : ($entry->reference['id'] ?? $entry->reference_id ?? null);
                                                @endphp

                                                @if ($invoiceId)
                                                    <a href="{{ route('admin.invoices.show', ['invoice' => $invoiceId]) }}"
                                                        class="text-primary hover:underline">
                                                        Invoice #{{ is_object($entry->reference) ? $entry->reference->invoice_number : ($entry->reference['invoice_number'] ?? $invoiceId) }}
                                                    </a>
                                                @else
                                                    Invoice #{{ $entry->reference_id ?? 'N/A' }}
                                                @endif
                                            @elseif ($referenceType === 'App\\Models\\TherapistBill')
                                                @php
                                                    $billId = is_object($entry->reference)
                                                        ? $entry->reference->id
                                                        : ($entry->reference['id'] ?? $entry->reference_id ?? null);
                                                @endphp

                                                @if ($billId)
                                                    <a href="{{ route('admin.billing.therapist-bills.show', ['bill' => $billId]) }}"
                                                        class="text-primary hover:underline">
                                                        Bill #{{ is_object($entry->reference) ? $entry->reference->bill_number : ($entry->reference['bill_number'] ?? $billId) }}
                                                    </a>
                                                @else
                                                    Bill #{{ $entry->reference_id ?? 'N/A' }}
                                                @endif
                                            @elseif ($referenceType === 'App\\Models\\InvoicePayment')
                                                @php
                                                    $invoiceFromPayment = null;

                                                    if (is_object($entry->reference)) {
                                                        $firstInvoice = $entry->reference->invoice()->first();
                                                        $invoiceFromPayment = $firstInvoice?->id;
                                                    }
                                                @endphp

                                                @if ($invoiceFromPayment)
                                                    <a href="{{ route('admin.invoices.show', ['invoice' => $invoiceFromPayment]) }}"
                                                        class="text-primary hover:underline">
                                                        Payment #{{ $entry->reference->id ?? $entry->reference_id ?? '' }}
                                                    </a>
                                                @else
                                                    Payment #{{ $entry->reference->id ?? $entry->reference_id ?? 'N/A' }}
                                                @endif
                                            @elseif ($referenceType === 'App\\Models\\TherapistBillPayment')
                                                @php
                                                    $billFromPayment = null;

                                                    if (is_object($entry->reference)) {
                                                        $firstBill = $entry->reference->therapistBill()->first();
                                                        $billFromPayment = $firstBill?->id;
                                                    }
                                                @endphp

                                                @if ($billFromPayment)
                                                    <a href="{{ route('admin.billing.therapist-bills.show', ['bill' => $billFromPayment]) }}"
                                                        class="text-primary hover:underline">
                                                        Payment #{{ $entry->reference->id ?? $entry->reference_id ?? '' }}
                                                    </a>
                                                @else
                                                    Payment #{{ $entry->reference->id ?? $entry->reference_id ?? 'N/A' }}
                                                @endif
                                            @else
                                                {{ class_basename($referenceType) }} #{{ $entry->reference_id }}
                                            @endif
                                        @else
                                            <span class="text-foreground/40">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        @if ($entry->amount < 0)
                                            <span class="font-semibold text-danger-600">
                                                ${{ number_format(abs($entry->amount), 2) }}
                                            </span>
                                        @else
                                            <span class="text-foreground/30">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        @if ($entry->amount > 0)
                                            <span class="font-semibold text-success-600">
                                                ${{ number_format($entry->amount, 2) }}
                                            </span>
                                        @else
                                            <span class="text-foreground/30">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        <span
                                            class="font-semibold {{ $entry->balance_after > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                            ${{ number_format(abs($entry->balance_after), 2) }}
                                            {{ $entry->balance_after > 0 ? 'DR' : 'CR' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        @if ($entry->notes)
                                            <span class="text-foreground/80">
                                                {{ Str::limit($entry->notes, 60) }}
                                            </span>
                                        @else
                                            <span class="text-foreground/30">—</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-sm">
                                        {{ $entry->recordedBy->name ?? 'System' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $ledgerEntries->links() }}
            </div>
        @else
            <x-ui::empty-state title="No transactions found"
                description="No transactions have been recorded for this account yet." />
        @endif
    </x-ui::card>

    {{-- Quick actions --}}
    <x-ui::card class="mt-6 p-4">
        <h5 class="text-sm font-semibold mb-3">Quick Actions</h5>
        <div class="flex flex-wrap gap-3">
            @if ($type === 'school')
                <a href="{{ route('admin.schools.show', $account) }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                    View School/Family Profile
                </a>
                <a href="{{ route('admin.invoices.index', ['school_id' => $account->id]) }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                    View All Invoices
                </a>
                <a href="{{ route('admin.invoices.create', ['school_id' => $account->id]) }}"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90">
                    + New Invoice
                </a>
            @else
                <a href="{{ route('admin.therapists.show', $account) }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                    View Therapist Profile
                </a>
                <a href="{{ route('admin.billing.therapist-bills.index', ['therapist_id' => $account->id]) }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                    View All Bills
                </a>
                <a href="{{ route('admin.billing.therapist-bills.create', ['therapist_id' => $account->id]) }}"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90">
                    + New Bill
                </a>
            @endif

            <button type="button" data-open-modal="creditNoteModal"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                + Credit Note
            </button>
            <button type="button" data-open-modal="refundModal"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                + Refund
            </button>
        </div>
    </x-ui::card>

    {{-- Credit Note Modal --}}
    <div id="creditNoteModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
        role="dialog" aria-modal="true" aria-labelledby="creditNoteModalTitle"
        data-ledger-adjustment-modal>
        <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 id="creditNoteModalTitle" class="text-lg font-semibold text-foreground">Create Credit Note</h2>
                <button type="button" data-close-modal="creditNoteModal"
                    class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                    aria-label="Close modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5">
                <p class="text-xs text-foreground/60 mb-4">
                    A credit note reduces what
                    {{ $type === 'school' ? 'the school owes you' : 'we owe the therapist' }}.
                    No cash moves.
                </p>
                <x-admin.ledger.adjustment-form
                    :type="$type"
                    :account-id="$account->id"
                    transaction-type="credit_note"
                    form-id="creditNoteForm" />
            </div>
        </div>
    </div>

    {{-- Refund Modal --}}
    <div id="refundModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
        role="dialog" aria-modal="true" aria-labelledby="refundModalTitle"
        data-ledger-adjustment-modal>
        <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 id="refundModalTitle" class="text-lg font-semibold text-foreground">Create Refund</h2>
                <button type="button" data-close-modal="refundModal"
                    class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                    aria-label="Close modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5">
                <p class="text-xs text-foreground/60 mb-4">
                    A refund records cash leaving your account back to
                    {{ $type === 'school' ? 'the school' : 'the therapist' }}.
                </p>
                <x-admin.ledger.adjustment-form
                    :type="$type"
                    :account-id="$account->id"
                    transaction-type="refund"
                    form-id="refundForm" />
            </div>
        </div>
    </div>

    {{-- Edit Adjustment Modal --}}
    <div id="editAdjustmentModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
        role="dialog" aria-modal="true" aria-labelledby="editAdjustmentModalTitle"
        data-ledger-adjustment-modal>
        <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
            <div class="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 id="editAdjustmentModalTitle" class="text-lg font-semibold text-foreground">Edit Adjustment</h2>
                <button type="button" data-close-modal="editAdjustmentModal"
                    class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                    aria-label="Close modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-5">
                <form id="editAdjustmentForm" data-ledger-adjustment-edit-form
                    method="POST" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="editAdjustmentRecordedAt" :value="'Transaction Date'" />
                        <p id="editAdjustmentRecordedAt_help" class="mt-1 text-xs text-foreground/60">
                            When this transaction occurred. Backdate if needed.
                        </p>
                        <x-text-input id="editAdjustmentRecordedAt" name="recorded_at" type="date"
                            :max="now()->toDateString()" required
                            aria-describedby="editAdjustmentRecordedAt_help"
                            class="mt-1 block w-full" />
                        <x-input-error :messages="[]" data-error-for="recorded_at" class="mt-1 hidden" />
                    </div>

                    <div>
                        <x-input-label for="editAdjustmentAmount" :value="'Amount'" />
                        <p id="editAdjustmentAmount_help" class="mt-1 text-xs text-foreground/60">
                            Enter amount in USD (e.g. 100.00).
                        </p>
                        <x-text-input id="editAdjustmentAmount" name="amount" type="number"
                            step="0.01" min="0.01" max="999999.99" required
                            aria-describedby="editAdjustmentAmount_help"
                            class="mt-1 block w-full" />
                        <x-input-error :messages="[]" data-error-for="amount" class="mt-1 hidden" />
                    </div>

                    <div>
                        <x-input-label for="editAdjustmentNotes" :value="'Notes (optional)'" />
                        <textarea id="editAdjustmentNotes" name="notes" rows="3" maxlength="500"
                            class="mt-1 block w-full border border-input bg-white text-foreground rounded-base shadow-sm focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>
                        <x-input-error :messages="[]" data-error-for="notes" class="mt-1 hidden" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" data-close-modal="editAdjustmentModal"
                            class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ledger-accounts-show.js'])
    </x-slot>
</x-admin.layouts.app>
