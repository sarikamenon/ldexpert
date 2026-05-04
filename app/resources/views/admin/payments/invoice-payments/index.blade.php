<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::page-header title="Invoice Payments" subtitle="Review payments received from schools or families">
    </x-ui::page-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    {{-- Summary (updated by JS when using server-side DataTables) --}}
    <x-ui::card class="p-6 mb-6" id="invoicePaymentsSummaryCard">
        <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
            <div>
                <p class="text-sm text-foreground/70">Total Payments</p>
                <p class="text-2xl font-bold mt-1" id="invoicePaymentsTotalAmount">$0.00</p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Number of Payments</p>
                <p class="text-2xl font-bold mt-1" id="invoicePaymentsCount">0</p>
            </div>
        </div>
    </x-ui::card>

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
        <form method="GET" action="{{ route('admin.payments.invoices.index') }}" id="invoicePaymentsFiltersForm"
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
                        Search by reference number or school/family name to quickly locate a specific payment.
                    </p>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                        placeholder="Reference or school/family" aria-describedby="search_help"
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

        <div class="overflow-x-auto">
            <table id="invoicePaymentsTable" class="w-full display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
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
                </tbody>
            </table>
        </div>
    </x-ui::card>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoice-payments-index.js'])
    </x-slot>
</x-admin.layouts.app>
