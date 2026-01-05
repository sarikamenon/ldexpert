<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Invoices</h1>
                <p class="text-sm text-foreground/60 mt-1">Manage and send invoices to schools</p>
            </div>
            <a href="{{ route('admin.invoices.create') }}"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Create Invoice
            </a>
        </div>
    </div>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="space-y-1">
                <label for="school_id" class="text-xs font-medium text-foreground/70">School</label>
                <select id="school_id" name="school_id"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary min-w-[10rem]">
                    <option value="">All Schools</option>
                    @foreach ($schools ?? [] as $school)
                        <option value="{{ $school->id }}" @selected((int) ($filters['school_id'] ?? 0) === $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label for="status" class="text-xs font-medium text-foreground/70">Status</label>
                <select id="status" name="status"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary min-w-[10rem]">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label for="date_from" class="text-xs font-medium text-foreground/70">From Date</label>
                <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="space-y-1">
                <label for="date_to" class="text-xs font-medium text-foreground/70">To Date</label>
                <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <div class="space-y-1">
                <label for="invoice_number" class="text-xs font-medium text-foreground/70">Invoice Number</label>
                <input type="text" id="invoice_number" name="invoice_number"
                    value="{{ $filters['invoice_number'] ?? '' }}" placeholder="Search..."
                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
            </div>

            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Filter
            </button>

            <a href="{{ route('admin.invoices.index') }}"
                class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                Clear
            </a>
        </form>

        @if ($invoices->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Invoice #</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">School</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Period</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Total</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Due Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr class="border-b border-border hover:bg-background/subtle">
                                <td class="py-3 px-4">
                                    <a href="{{ route('admin.invoices.show', $invoice) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td class="py-3 px-4">{{ $invoice->school_display_name ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm text-foreground/70">
                                    {{ $invoice->billing_period_start->format('M d') }} -
                                    {{ $invoice->billing_period_end->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4 font-medium">${{ number_format($invoice->total, 2) }}</td>
                                <td class="py-3 px-4">
                                    <x-ui::badge :variant="match ($invoice->status) {
                                        \App\Enums\InvoiceStatus::DRAFT => 'secondary',
                                        \App\Enums\InvoiceStatus::SENT => 'primary',
                                        \App\Enums\InvoiceStatus::PAID => 'success',
                                        default => 'secondary',
                                    }">
                                        {{ $invoice->status?->label() }}
                                    </x-ui::badge>
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground/70">
                                    {{ $invoice->due_date->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.invoices.show', $invoice) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors"
                                            title="View Invoice">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.invoices.download', $invoice) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors"
                                            title="Download PDF">
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

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        @else
            <div class="text-center py-12 text-foreground/60">
                <p>No invoices found.</p>
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>
