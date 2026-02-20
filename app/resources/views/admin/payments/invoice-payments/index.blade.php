<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::page-header title="Invoice Payments" subtitle="Review payments received from schools">
    </x-ui::page-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    {{-- Summary --}}
    @if ($payments->count() > 0)
        <x-ui::card class="p-6 mb-6">
            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <p class="text-sm text-foreground/70">Total Payments</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($totalAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-foreground/70">Number of Payments</p>
                    <p class="text-2xl font-bold mt-1">{{ $payments->total() }}</p>
                </div>
            </div>
        </x-ui::card>
    @endif

    {{-- Payments List --}}
    <x-ui::card class="p-6 space-y-4 overflow-hidden">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold text-foreground">
                Invoice Payments
            </h2>
            <div class="hidden md:flex items-center gap-2">
                <button type="button"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle"
                    disabled
                    aria-disabled="true">
                    Export
                </button>
                <a href="{{ route('admin.payments.invoices.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Record Payment
                </a>
            </div>
        </div>

        {{-- Filters + mobile actions --}}
        <form method="GET" action="{{ route('admin.payments.invoices.index') }}"
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 flex-1">
                <div>
                    <x-input-label for="from_date" value="From Date" />
                    <p class="mt-1 text-xs text-foreground/60" id="from_date_help">
                        Filter payments from this date onward. Leave blank to include all earlier payments.
                    </p>
                    <input type="date" id="from_date" name="from_date" value="{{ request('from_date') }}"
                        aria-describedby="from_date_help"
                        class="mt-1 w-full px-3 py-2 border border-border rounded-md text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                </div>

                <div>
                    <x-input-label for="to_date" value="To Date" />
                    <p class="mt-1 text-xs text-foreground/60" id="to_date_help">
                        Filter payments up to and including this date. Leave blank to include recent payments.
                    </p>
                    <input type="date" id="to_date" name="to_date" value="{{ request('to_date') }}"
                        aria-describedby="to_date_help"
                        class="mt-1 w-full px-3 py-2 border border-border rounded-md text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                </div>

                <div>
                    <x-input-label for="method" value="Payment Method" />
                    <p class="mt-1 text-xs text-foreground/60" id="method_help">
                        Narrow results to payments recorded with a specific method, such as check or bank transfer.
                    </p>
                    <select id="method" name="method" aria-describedby="method_help"
                        class="mt-1 w-full px-3 py-2 border border-border rounded-md text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                        <option value="">All Methods</option>
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}" @selected(request('method') === $method->value)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="search" value="Search" />
                    <p class="mt-1 text-xs text-foreground/60" id="search_help">
                        Search by reference number or school name to quickly locate a specific payment.
                    </p>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                        placeholder="Reference or School" aria-describedby="search_help"
                        class="mt-1 w-full px-3 py-2 border border-border rounded-md text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 md:justify-end">
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Apply Filters
                </button>
                <a href="{{ route('admin.payments.invoices.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Clear Filters
                </a>

                {{-- Mobile Export / Add --}}
                <div class="flex items-center gap-2 md:hidden mt-2">
                    <button type="button"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle"
                        disabled
                        aria-disabled="true">
                        Export
                    </button>
                    <a href="{{ route('admin.payments.invoices.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Record Payment
                    </a>
                </div>
            </div>
        </form>

        @if ($payments->count() > 0)
            <div class="overflow-x-auto">
                <table id="invoicePaymentsTable" class="w-full display">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Invoice</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Amount</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Method</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->paid_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @if ($payment->invoice)
                                        <a href="{{ route('admin.invoices.show', $payment->invoice) }}"
                                            class="text-primary hover:underline">
                                            {{ $payment->invoice->invoice_number }}
                                        </a>
                                        <span class="text-foreground/60"> — {{ $payment->school?->name ?? $payment->invoice->school_name ?? '—' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm text-right font-medium text-success-600">
                                    ${{ number_format($payment->amount, 2) }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ $payment->method->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->reference ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->recordedBy->name ?? 'System' }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST"
                                            action="{{ route('admin.payments.invoices.destroy', $payment) }}"
                                            class="inline js-invoice-payment-delete-form"
                                            data-confirm-title="Delete invoice payment?"
                                            data-confirm-text="This will remove all allocations and the related ledger entry. This action cannot be undone.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                title="Delete Payment"
                                                aria-label="Delete invoice payment #{{ $payment->id }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6">
                                                    </path>
                                                    <path d="M10 11v6"></path>
                                                    <path d="M14 11v6"></path>
                                                    <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2">
                                                    </path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui::empty-state title="No invoice payments found"
                description="No invoice payments match your current filters. Try adjusting your search criteria." />
        @endif
    </x-ui::card>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoice-payments-index.js'])
    </x-slot>
</x-admin.layouts.app>
