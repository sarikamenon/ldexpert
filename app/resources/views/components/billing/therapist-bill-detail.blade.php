@props(['bill'])

<x-ui::card class="p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="text-sm font-medium text-foreground/70 mb-2">Bill To</h3>
            <div class="text-sm text-foreground">
                <p class="font-medium">{{ $bill->therapist_name }}</p>
                @if ($bill->therapist_address)
                    <p class="mt-1">{{ $bill->therapist_address }}</p>
                @endif
                @if ($bill->therapist_email)
                    <p class="mt-1">{{ $bill->therapist_email }}</p>
                @endif
                @if ($bill->therapist_phone)
                    <p class="mt-1">{{ $bill->therapist_phone }}</p>
                @endif
            </div>
        </div>

        <div>
            <h3 class="text-sm font-medium text-foreground/70 mb-2">From</h3>
            <div class="text-sm text-foreground">
                <p class="font-medium">{{ $bill->company_name }}</p>
                @if ($bill->company_address)
                    <p class="mt-1">{!! nl2br(e($bill->company_address)) !!}</p>
                @endif
                @if ($bill->company_phone)
                    <p>{{ $bill->company_phone }}</p>
                @endif
                @if ($bill->company_email)
                    <p class="mt-1">{{ $bill->company_email }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-border">
        <div>
            <p class="text-sm text-foreground/70">Bill Date</p>
            <p class="text-sm font-medium mt-1">{{ $bill->bill_date->format('M d, Y') }}</p>
        </div>
        @if ($bill->sent_at)
            <div>
                <p class="text-sm text-foreground/70">Sent At</p>
                <p class="text-sm font-medium mt-1">{{ $bill->sent_at->format(config('display.datetime')) }}</p>
            </div>
        @endif
    </div>
</x-ui::card>

<x-ui::card class="p-6">
    <h2 class="text-lg font-semibold text-foreground mb-4">Line Items</h2>

    @if ($bill->sessionLogs->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">School/Family</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Rate</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bill->sessionLogs as $log)
                        <tr class="border-b border-border hover:bg-background/subtle">
                            <td class="py-3 px-4 text-sm">{{ $log->session_date->format('M d, Y') }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->student->name ?? '—' }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->service->name ?? '—' }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->school->display_name ?? '—' }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->duration_minutes }} min</td>
                            <td class="py-3 px-4 text-sm text-right">
                                ${{ number_format($log->therapist_rate_amount ?? 0, 2) }}</td>
                            <td class="py-3 px-4 text-sm text-right font-medium">
                                ${{ number_format($log->therapist_billable_amount ?? 0, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-border">
                        <td colspan="6" class="py-3 px-4 text-right text-sm font-medium text-foreground/70">
                            Subtotal:</td>
                        <td class="py-3 px-4 text-right text-sm font-medium">
                            ${{ number_format($bill->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="6" class="py-3 px-4 text-right text-sm font-medium text-foreground/70">
                            Adjustments:
                        </td>
                        <td class="py-3 px-4 text-right text-sm font-medium">
                            ${{ number_format($bill->adjustments_total, 2) }}</td>
                    </tr>
                    <tr class="bg-background/subtle">
                        <td colspan="6" class="py-3 px-4 text-right text-lg font-semibold">Total Due:</td>
                        <td class="py-3 px-4 text-right text-lg font-semibold">
                            ${{ number_format($bill->total_due, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <p class="text-foreground/60">No line items found.</p>
    @endif

    @if ($bill->notes)
        <div class="mt-6 pt-6 border-t border-border">
            <h3 class="text-sm font-medium text-foreground/70 mb-2">Notes</h3>
            <p class="text-sm text-foreground/80">{{ $bill->notes }}</p>
        </div>
    @endif
    </x-ui::card>

{{-- Record Payment Modal --}}
@if (!$bill->isDraft())
    <div x-data="{ open: false }" x-on:open-record-payment-modal.window="open = true" x-show="open"
        class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" role="dialog" aria-modal="true"
        aria-labelledby="record-payment-modal-title-bill">
        <div class="fixed inset-0 bg-foreground/50" x-on:click="open = false" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-full items-center justify-center px-4 py-8 sm:py-10">
            <div
                class="flex w-full max-w-md flex-col overflow-hidden rounded-lg border border-border bg-background shadow-xl outline-none focus:outline-none">
                <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
                    <h3 id="record-payment-modal-title-bill" class="text-lg font-semibold text-foreground">Record Payment
                    </h3>
                    <button type="button"
                        class="shrink-0 rounded-md p-2 text-foreground/70 transition-colors hover:bg-background/subtle hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
                        x-on:click="open = false" aria-label="Close dialog">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.billing.therapist-bills.payments.store', $bill) }}"
                    class="flex flex-col px-6 pt-4 pb-8">
                    @csrf
                    <input type="hidden" name="therapist_bill_id" value="{{ $bill->id }}">

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
                                value="{{ old('amount', $bill->balance_remaining) }}" required
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
                            <label class="block text-sm font-medium text-foreground mb-1">Reference Number</label>
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

                    <div class="mt-6 flex gap-3 border-t border-border pt-6">
                        <button type="button" x-on:click="open = false"
                            class="flex-1 px-4 py-2 border border-border rounded-md hover:bg-background/subtle focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring">
                            Cancel
                        </button>
                        <button type="submit"
                            class="flex-1 px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 active:bg-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
