<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-show.js'])
    </x-slot>

    <x-ui::show-header :title="'Invoice ' . $invoice->invoice_number"
        :subtitle="'Billing Period: ' . $invoice->billing_period_start->format('M d') . ' - ' . $invoice->billing_period_end->format('M d, Y')"
        :back-url="route('admin.invoices.index')" back-label="Back to List">
        <x-slot name="badge">
            <x-ui::badge :variant="match ($invoice->status) {
                \App\Enums\InvoiceStatus::DRAFT => 'secondary',
                \App\Enums\InvoiceStatus::SENT => 'primary',
                \App\Enums\InvoiceStatus::PAID => 'success',
                default => 'secondary',
            }">
                {{ $invoice->status?->label() }}
            </x-ui::badge>
        </x-slot>
        <x-slot name="actions">
            <a href="{{ route('admin.invoices.download', $invoice) }}">
                <x-ui::button>
                    Download PDF
                </x-ui::button>
            </a>
            @if ($invoice->isDraft())
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" class="inline"
                    x-data="{ loading: false }" x-on:submit="loading = true">
                    @csrf
                    <x-ui::button type="submit" variant="success" x-bind:disabled="loading">
                        <span data-label x-show="!loading">Send Invoice</span>
                        <span data-loading x-show="loading" class="inline-flex items-center gap-2 hidden">
                            <svg class="animate-spin h-4 w-4 text-success-foreground"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Sending...
                        </span>
                    </x-ui::button>
                </form>
            @endif
        </x-slot>
    </x-ui::show-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Bill To</h3>
                <div class="text-sm text-foreground">
                    <p class="font-medium">{{ $invoice->school_display_name ?? $invoice->school_name }}</p>
                    @if ($invoice->school_address)
                        <p class="mt-1">{{ $invoice->school_address }}</p>
                    @endif
                    @if ($invoice->school_state)
                        <p>{{ $invoice->school_state }}</p>
                    @endif
                    @if ($invoice->school_contact_email)
                        <p class="mt-1">{{ $invoice->school_contact_email }}</p>
                    @endif
                </div>
            </div>

            <div>
                <h3 class="text-sm font-medium text-foreground/70 mb-2">From</h3>
                <div class="text-sm text-foreground">
                    <p class="font-medium">{{ $invoice->company_name }}</p>
                    @if ($invoice->company_address)
                        <p class="mt-1">{{ $invoice->company_address }}</p>
                    @endif
                    @if ($invoice->company_phone)
                        <p>{{ $invoice->company_phone }}</p>
                    @endif
                    @if ($invoice->company_email)
                        <p class="mt-1">{{ $invoice->company_email }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-border">
            <div>
                <p class="text-sm text-foreground/70">Invoice Date</p>
                <p class="text-sm font-medium mt-1">{{ $invoice->created_at->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Due Date</p>
                <p class="text-sm font-medium mt-1">{{ $invoice->due_date->format('M d, Y') }}</p>
            </div>
            @if ($invoice->sent_at)
                <div>
                    <p class="text-sm text-foreground/70">Sent At</p>
                    <p class="text-sm font-medium mt-1">{{ $invoice->sent_at->format('M d, Y h:i A') }}</p>
                </div>
            @endif
        </div>
    </x-ui::card>

    <x-ui::card class="p-6">
        <h2 class="text-lg font-semibold text-foreground mb-4">Line Items</h2>

        @if ($invoice->sessionLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Therapist</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Rate</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->sessionLogs as $log)
                            <tr class="border-b border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">{{ $log->session_date->format('M d, Y') }}</td>
                                <td class="py-3 px-4 text-sm">{{ $log->student->name ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm">{{ $log->service->name ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm">{{ $log->therapist->name ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm">{{ $log->duration_minutes }} min</td>
                                <td class="py-3 px-4 text-sm text-right">
                                    ${{ number_format($log->school_rate_amount ?? 0, 2) }}</td>
                                <td class="py-3 px-4 text-sm text-right font-medium">
                                    ${{ number_format($log->school_invoice_amount ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-border">
                            <td colspan="6" class="py-3 px-4 text-right text-sm font-medium text-foreground/70">
                                Subtotal:</td>
                            <td class="py-3 px-4 text-right text-sm font-medium">
                                ${{ number_format($invoice->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="py-3 px-4 text-right text-sm font-medium text-foreground/70">Tax:
                            </td>
                            <td class="py-3 px-4 text-right text-sm font-medium">
                                ${{ number_format($invoice->tax_total, 2) }}</td>
                        </tr>
                        <tr class="bg-background/subtle">
                            <td colspan="6" class="py-3 px-4 text-right text-lg font-semibold">Total:</td>
                            <td class="py-3 px-4 text-right text-lg font-semibold">
                                ${{ number_format($invoice->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <x-ui::empty-state title="No line items found."
                description="This invoice does not currently include any session logs. Add session logs to see billing details here." />
        @endif

        @if ($invoice->notes)
            <div class="mt-6 pt-6 border-t border-border">
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Notes</h3>
                <p class="text-sm text-foreground/80">{{ $invoice->notes }}</p>
            </div>
        @endif
    </x-ui::card>

    {{-- Payment Information --}}
    @if (!$invoice->isDraft())
        <x-ui::card class="p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-foreground">Payment Information</h2>
                @if (!$invoice->isPaid())
                    <button type="button" x-data="" x-on:click="$dispatch('open-record-payment-modal')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Record Payment
                    </button>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-background/subtle rounded-lg">
                <div>
                    <p class="text-sm text-foreground/70">Total Amount</p>
                    <p class="text-lg font-semibold mt-1">${{ number_format($invoice->total, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-foreground/70">Amount Paid</p>
                    <p class="text-lg font-semibold mt-1 text-success">
                        ${{ number_format($invoice->total_paid, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-foreground/70">Balance Remaining</p>
                    <p class="text-lg font-semibold mt-1 {{ $invoice->balance_remaining > 0 ? 'text-warning' : 'text-success' }}">
                        ${{ number_format($invoice->balance_remaining, 2) }}</p>
                </div>
            </div>

            @php
                $allocations = $invoice->paymentAllocations()->with(['payment.recordedBy'])->latest()->get();
            @endphp

            @if ($allocations->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Method</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Reference</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Recorded By</th>
                                <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($allocations as $allocation)
                                @php
                                    $payment = $allocation->payment;
                                @endphp
                                <tr class="border-b border-border hover:bg-background/subtle">
                                    <td class="py-3 px-4 text-sm">{{ $payment->paid_at->format('M d, Y') }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $payment->method?->label() }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $payment->reference ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm text-right font-medium">
                                        ${{ number_format($allocation->allocated_amount, 2) }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $payment->recordedBy?->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm text-right">
                                        @can('delete', $payment)
                                            <form method="POST"
                                                action="{{ route('admin.invoices.payments.destroy', [$invoice, $payment]) }}"
                                                class="inline" x-data="{ confirmDelete: false }"
                                                x-on:submit.prevent="if (confirmDelete || confirm('Are you sure you want to delete this payment?')) $el.submit()">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-danger hover:text-danger/80 text-sm">Delete</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui::empty-state title="No payments recorded"
                    description="Click 'Record Payment' above to add a payment for this invoice." />
            @endif
        </x-ui::card>

        {{-- Record Payment Modal --}}
        <div x-data="{ open: false }" x-on:open-record-payment-modal.window="open = true" x-show="open"
            class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black opacity-50" x-on:click="open = false"></div>

                <div class="relative bg-card rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Record Payment</h3>

                    <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}">
                        @csrf

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Payment Date *</label>
                                <input type="date" name="paid_at" value="{{ old('paid_at', date('Y-m-d')) }}"
                                    max="{{ date('Y-m-d') }}" required
                                    class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                                @error('paid_at')
                                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Amount *</label>
                                <input type="number" name="amount" step="0.01" min="0.01"
                                    value="{{ old('amount', $invoice->balance_remaining) }}" required
                                    class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                                @error('amount')
                                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Payment Method *</label>
                                <select name="method" required
                                    class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                                    <option value="">Select method...</option>
                                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                        <option value="{{ $method->value }}"
                                            {{ old('method') == $method->value ? 'selected' : '' }}>
                                            {{ $method->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('method')
                                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Reference
                                    Number</label>
                                <input type="text" name="reference" value="{{ old('reference') }}"
                                    placeholder="Check number, transaction ID, etc."
                                    class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                                @error('reference')
                                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Notes</label>
                                <textarea name="notes" rows="3"
                                    class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <p class="text-sm text-danger mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="button" x-on:click="open = false"
                                class="flex-1 px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                                Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-admin.layouts.app>
