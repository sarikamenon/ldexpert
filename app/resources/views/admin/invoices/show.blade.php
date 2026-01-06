<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-show.js'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Invoice {{ $invoice->invoice_number }}</h1>
                <p class="text-sm text-foreground/60 mt-1">Billing Period:
                    {{ $invoice->billing_period_start->format('M d') }} -
                    {{ $invoice->billing_period_end->format('M d, Y') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui::badge :variant="match ($invoice->status) {
                    \App\Enums\InvoiceStatus::DRAFT => 'secondary',
                    \App\Enums\InvoiceStatus::SENT => 'primary',
                    \App\Enums\InvoiceStatus::PAID => 'success',
                    default => 'secondary',
                }">
                    {{ $invoice->status?->label() }}
                </x-ui::badge>
                <a href="{{ route('admin.invoices.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                    Back to List
                </a>
                <a href="{{ route('admin.invoices.download', $invoice) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Download PDF
                </a>
                @if ($invoice->isDraft())
                    <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" class="inline">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium">
                            Send Invoice
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

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
            <p class="text-foreground/60">No line items found.</p>
        @endif

        @if ($invoice->notes)
            <div class="mt-6 pt-6 border-t border-border">
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Notes</h3>
                <p class="text-sm text-foreground/80">{{ $invoice->notes }}</p>
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>
