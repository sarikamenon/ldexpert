@if (!$invoice->isDraft())
    <div x-data="{ open: false }" x-on:open-record-payment-modal.window="open = true" x-show="open"
        class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" role="dialog" aria-modal="true"
        aria-labelledby="record-payment-modal-title">
        <div class="fixed inset-0 bg-foreground/50" x-on:click="open = false" aria-hidden="true"></div>

        <div class="relative z-10 flex min-h-full items-center justify-center px-4 py-8 sm:py-10">
            <div
                class="flex w-full max-w-md flex-col overflow-hidden rounded-lg border border-border bg-background shadow-xl outline-none focus:outline-none">
                <div class="flex items-start justify-between gap-4 border-b border-border px-6 py-4">
                    <h3 id="record-payment-modal-title" class="text-lg font-semibold text-foreground">Record Payment
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

                <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}"
                    class="flex flex-col px-6 pt-4 pb-8">
                    @csrf
                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

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
