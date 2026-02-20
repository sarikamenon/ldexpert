<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Invoices" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="invoiceFiltersForm">
            <x-slot:filters>
                <x-ui::select name="school_id" searchable placeholder="All Schools" :inline="true" class="w-40">
                    <option value="">All Schools</option>
                    @foreach ($schools ?? [] as $school)
                        <option value="{{ $school->id }}" @selected((int) ($filters['school_id'] ?? 0) === $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true" class="w-36">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                <x-ui::input type="text" name="invoice_number" value="{{ $filters['invoice_number'] ?? '' }}"
                    placeholder="Invoice #" class="w-32" />

                @if (!empty(array_filter($filters ?? [])))
                    <a href="{{ route('admin.invoices.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.invoices.create') }}">
                    <x-ui::button>Create Invoice</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        @if ($invoices->count() > 0)
            <div class="overflow-x-auto">
                <table id="invoicesTable" class="w-full display">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>School</th>
                            <th>Period</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $invoice) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors"
                                        title="View Invoice">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $invoice) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $invoice->school_display_name ?? '—' }}
                                    </a>
                                </td>
                                <td class="text-sm text-foreground/70">
                                    {{ $invoice->billing_period_start->format('M d') }} -
                                    {{ $invoice->billing_period_end->format('M d, Y') }}
                                </td>
                                <td class="font-medium">
                                    ${{ number_format($invoice->total, 2) }}
                                </td>
                                <td>
                                    <x-ui::badge :variant="match ($invoice->status) {
                                        \App\Enums\InvoiceStatus::DRAFT => 'secondary',
                                        \App\Enums\InvoiceStatus::SENT => 'primary',
                                        \App\Enums\InvoiceStatus::PAID => 'success',
                                        default => 'secondary',
                                    }">
                                        {{ $invoice->status?->label() }}
                                    </x-ui::badge>
                                </td>
                                <td class="text-sm text-foreground/70">
                                    {{ $invoice->due_date->format('M d, Y') }}
                                </td>
                                <td>
                                    <div class="flex space-x-1">
                                        <a href="{{ route('admin.invoices.show', $invoice) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="View Invoice"
                                            aria-label="View invoice {{ $invoice->invoice_number }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.invoices.download', $invoice) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="Download PDF"
                                            aria-label="Download invoice {{ $invoice->invoice_number }} as PDF">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                </path>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12 text-foreground/60">
                <p>No invoices found.</p>
            </div>
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-index.js'])
    </x-slot>
</x-admin.layouts.app>
