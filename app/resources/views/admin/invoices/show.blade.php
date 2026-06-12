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
            @if ($invoice->isDraft())
                <a href="{{ route('admin.invoices.attach-sessions', $invoice) }}">
                    <x-ui::button variant="secondary">
                        Add or remove sessions
                    </x-ui::button>
                </a>
            @endif

            @if (!$invoice->isDraft() && !$invoice->isPaid())
                <x-ui::button type="button" x-data=""
                    x-on:click="$dispatch('open-record-payment-modal')">
                    Record Payment
                </x-ui::button>
            @endif

            @if ($invoice->isSent() && !$invoice->isPaid())
                <x-ui::button type="button" variant="secondary" x-data=""
                    x-on:click="$dispatch('open-resend-email-modal')">
                    Resend Email
                </x-ui::button>
            @endif

            <a href="{{ route('admin.invoices.download', $invoice) }}">
                <x-ui::button>
                    Download PDF
                </x-ui::button>
            </a>

            @if ($invoice->isDraft())
                {{-- Send delivers the invoice to the school/family's invoice email when
                     one is on file; otherwise it just marks the invoice as sent without
                     emailing. Either way it is a single confirmed action — no modal. --}}
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}" id="send-email-form"
                    class="inline">
                    @csrf
                    <x-ui::button type="submit" variant="success" id="send-invoice-button"
                        data-is-private-family="{{ ($invoice->school?->is_private_student ?? false) ? '1' : '0' }}"
                        data-invoice-total="{{ $invoice->total }}"
                        data-has-invoice-email="{{ filled($invoice->school_invoice_email) ? '1' : '0' }}"
                        data-attach-sessions-url="{{ route('admin.invoices.attach-sessions', $invoice) }}">
                        Send Invoice
                    </x-ui::button>
                </form>
            @endif
        </x-slot>
    </x-ui::show-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error') || $errors->has('error'))
        <x-ui::alert variant="danger" class="mb-4">
            {{ session('error') ?? $errors->first('error') }}
            @if (! $invoice->school_invoice_email && $invoice->school_id)
                <a href="{{ route('admin.schools.edit', $invoice->school_id) }}"
                    class="font-medium underline hover:no-underline">Edit school/family to add an invoice email</a>.
            @endif
        </x-ui::alert>
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
                    <p class="mt-1">Email: {{ $invoice->school_invoice_email }}</p>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-medium text-foreground/70 mb-2">From</h3>
                <div class="text-sm text-foreground">
                    <p class="font-medium">{{ $invoice->company_name }}</p>
                    @if ($invoice->company_address)
                        <p class="mt-1">{!! nl2br(e($invoice->company_address)) !!}</p>
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
                <p class="text-sm font-medium mt-1">{{ $invoice->invoice_date->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Due Date</p>
                <p class="text-sm font-medium mt-1">{{ $invoice->due_date->format('M d, Y') }}</p>
            </div>
            @if ($invoice->sent_at)
                <div>
                    <p class="text-sm text-foreground/70">Sent At</p>
                    <p class="text-sm font-medium mt-1">{{ $invoice->sent_at->format(config('display.datetime')) }}</p>
                </div>
            @endif
        </div>
    </x-ui::card>

    @include('admin.invoices._email-history')

    <x-ui::card class="p-6">
        <h2 class="text-lg font-semibold text-foreground mb-4">Line Items</h2>

        @if ($invoice->isAdvanceMode())
            @if ($adjustmentLines->isNotEmpty() || $advanceLines->isNotEmpty() || $standardLines->isNotEmpty())
                @if ($adjustmentLines->isNotEmpty())
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">Adjustments from Previous Period</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Description</th>
                                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($adjustmentLines as $line)
                                        <tr class="border-b border-border hover:bg-background/subtle">
                                            <td class="py-3 px-4 text-sm">{{ $line->description }}</td>
                                            <td class="py-3 px-4 text-sm text-right font-medium {{ (float) $line->total < 0 ? 'text-danger' : '' }}">
                                                {{ (float) $line->total < 0 ? '-' : '' }}${{ number_format(abs((float) $line->total), 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-border">
                                        <td class="py-3 px-4 text-right text-sm font-medium text-foreground/70">Adjustment Subtotal:</td>
                                        <td class="py-3 px-4 text-right text-sm font-medium {{ $adjustmentSubtotal < 0 ? 'text-danger' : '' }}">
                                            {{ $adjustmentSubtotal < 0 ? '-' : '' }}${{ number_format(abs($adjustmentSubtotal), 2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($advanceLines->isNotEmpty())
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">
                            Advance Charges for Upcoming Period
                            ({{ $advanceLines->first()->billing_period_start->format('M d') }} –
                            {{ $advanceLines->first()->billing_period_end->format('M d, Y') }})
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Description</th>
                                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Rate</th>
                                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($advanceLines as $line)
                                        <tr class="border-b border-border hover:bg-background/subtle">
                                            <td class="py-3 px-4 text-sm">{{ $line->description }}</td>
                                            <td class="py-3 px-4 text-sm text-right">${{ number_format((float) $line->unit_price, 2) }}</td>
                                            <td class="py-3 px-4 text-sm text-right font-medium">${{ number_format((float) $line->total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-border">
                                        <td colspan="2" class="py-3 px-4 text-right text-sm font-medium text-foreground/70">Advance Subtotal:</td>
                                        <td class="py-3 px-4 text-right text-sm font-medium">${{ number_format($advanceSubtotal, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($standardLines->isNotEmpty())
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">Session Charges</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Description</th>
                                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($standardLines as $line)
                                        <tr class="border-b border-border hover:bg-background/subtle">
                                            <td class="py-3 px-4 text-sm">{{ $line->description }}</td>
                                            <td class="py-3 px-4 text-sm text-right font-medium">${{ number_format((float) $line->total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="mt-6 pt-6 border-t-2 border-border">
                    <div class="ml-auto max-w-sm space-y-2">
                        @if ($adjustmentLines->isNotEmpty())
                            <div class="flex justify-between text-sm">
                                <span class="text-foreground/70">Adjustments:</span>
                                <span class="font-medium {{ $adjustmentSubtotal < 0 ? 'text-danger' : '' }}">
                                    {{ $adjustmentSubtotal < 0 ? '-' : '' }}${{ number_format(abs($adjustmentSubtotal), 2) }}
                                </span>
                            </div>
                        @endif
                        @if ($advanceLines->isNotEmpty())
                            <div class="flex justify-between text-sm">
                                <span class="text-foreground/70">Advance Charges:</span>
                                <span class="font-medium">${{ number_format($advanceSubtotal, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between pt-2 border-t border-border text-lg font-semibold">
                            <span>Total Due:</span>
                            <span>${{ number_format((float) $invoice->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                @if ((float) $invoice->carry_forward_balance > 0)
                    <div class="mt-4 p-3 rounded-md bg-warning/10 border border-warning/30 text-sm text-foreground/80">
                        <strong>Credit Balance:</strong>
                        ${{ number_format((float) $invoice->carry_forward_balance, 2) }}
                        will be applied to the next invoice.
                    </div>
                @endif
            @else
                <x-ui::empty-state title="No line items found."
                    description="This advance invoice does not yet have any line items." />
            @endif
        @elseif ($invoice->sessionLogs->count() > 0)
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

    {{-- Record Payment Modal --}}
    @include('admin.invoices._record-payment-modal')

    {{-- Resend Email Modal --}}
    @if ($invoice->isSent() && !$invoice->isPaid())
        <div x-data="{ open: false }" x-on:open-resend-email-modal.window="open = true" x-show="open"
            class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" role="dialog" aria-modal="true"
            aria-labelledby="resend-email-modal-title">
            <div class="fixed inset-0 bg-foreground/50" x-on:click="open = false" aria-hidden="true"></div>

            <div class="relative z-10 flex min-h-full items-center justify-center px-4 py-8 sm:py-10">
                <div
                    class="flex w-full max-w-md flex-col overflow-hidden rounded-lg border border-border bg-background shadow-xl outline-none focus:outline-none">
                    <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
                        <h3 id="resend-email-modal-title" class="text-lg font-semibold text-foreground">Resend Invoice
                            Email</h3>
                        <button type="button"
                            class="shrink-0 rounded-md p-2 text-foreground/70 transition-colors hover:bg-background/subtle hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
                            x-on:click="open = false" aria-label="Close dialog">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.invoices.resend-email', $invoice) }}"
                        id="resend-email-form" class="flex flex-col px-6 pt-4 pb-8">
                        @csrf

                        <div class="space-y-4">
                            <div>
                                <x-input-label for="resend_email" value="Recipient Email *" />
                                <p class="mt-1 text-xs text-foreground/60" id="resend_email_help">
                                    The email address to send the invoice to. Change if the original was incorrect.
                                </p>
                                <x-text-input id="resend_email" name="email" type="email"
                                    class="mt-1 block w-full"
                                    aria-describedby="resend_email_help"
                                    value="{{ old('email', $invoice->school_invoice_email) }}"
                                    required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="resend_message" value="Custom Message" />
                                <p class="mt-1 text-xs text-foreground/60" id="resend_message_help">
                                    Optional message to include in the email body. Leave blank to use the default.
                                </p>
                                <textarea id="resend_message" name="message" rows="3"
                                    aria-describedby="resend_message_help"
                                    class="mt-1 block w-full border-border rounded-md shadow-sm focus:ring-2 focus:ring-ring">{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex gap-3 border-t border-border pt-6">
                            <button type="button" x-on:click="open = false"
                                class="flex-1 px-4 py-2 border border-border rounded-md hover:bg-background/subtle focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 active:bg-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring">
                                Resend Email
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-admin.layouts.app>
