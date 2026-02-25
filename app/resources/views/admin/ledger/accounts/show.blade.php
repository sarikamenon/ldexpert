<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::show-header :title="$accountName" :subtitle="$accountType . ' Account Ledger'"
        :back-url="route('admin.ledger.accounts.index', ['type' => $type === 'school' ? 'schools' : 'therapists'])"
        back-label="Back to Accounts" />

    @php
        $lastEntry = $ledgerEntries->first();
        $balance = $lastEntry ? $lastEntry->balance_after : 0;
    @endphp

    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
                    {{ $balance < 0 ? 'DR' : 'CR' }}
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
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Transaction Type</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Notes</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
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
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Transaction Type</th>
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
                                            class="font-semibold {{ $entry->balance_after >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                            ${{ number_format(abs($entry->balance_after), 2) }}
                                            {{ $entry->balance_after < 0 ? 'DR' : 'CR' }}
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
                    View School Profile
                </a>
                <a href="{{ route('admin.invoices.index', ['school_id' => $account->id]) }}"
                    class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                    View All Invoices
                </a>
                <a href="{{ route('admin.invoices.create', ['school_id' => $account->id]) }}"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90">
                    Create New Invoice
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
                    Create New Bill
                </a>
            @endif
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ledger-accounts-show.js'])
    </x-slot>
</x-admin.layouts.app>
