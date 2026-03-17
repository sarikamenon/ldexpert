@if (!$invoice->isDraft())
    <div x-data="{ open: false }" x-on:open-record-payment-modal.window="open = true" x-show="open"
        class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-50" x-on:click="open = false"></div>

            <div class="relative bg-card rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-foreground mb-4">Record Payment</h3>

                <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}">
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
