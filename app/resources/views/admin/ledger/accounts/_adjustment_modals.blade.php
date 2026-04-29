{{-- Modal dialogs for creating/editing credit notes & refunds. --}}

{{-- Credit Note Modal --}}
<div id="creditNoteModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="creditNoteModalTitle"
    data-ledger-adjustment-modal>
    <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="creditNoteModalTitle" class="text-lg font-semibold text-foreground">Create Credit Note</h2>
            <button type="button" data-close-modal="creditNoteModal"
                class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5">
            <p class="text-xs text-foreground/60 mb-4">
                A credit note reduces what
                {{ $type === 'school' ? 'the school owes you' : 'we owe the therapist' }}.
                No cash moves.
            </p>
            <x-admin.ledger.adjustment-form
                :type="$type"
                :account-id="$account->id"
                transaction-type="credit_note"
                form-id="creditNoteForm" />
        </div>
    </div>
</div>

{{-- Refund Modal --}}
<div id="refundModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="refundModalTitle"
    data-ledger-adjustment-modal>
    <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="refundModalTitle" class="text-lg font-semibold text-foreground">Create Refund</h2>
            <button type="button" data-close-modal="refundModal"
                class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5">
            <p class="text-xs text-foreground/60 mb-4">
                A refund records cash leaving your account back to
                {{ $type === 'school' ? 'the school' : 'the therapist' }}.
            </p>
            <x-admin.ledger.adjustment-form
                :type="$type"
                :account-id="$account->id"
                transaction-type="refund"
                form-id="refundForm" />
        </div>
    </div>
</div>

{{-- Edit Adjustment Modal --}}
<div id="editAdjustmentModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="editAdjustmentModalTitle"
    data-ledger-adjustment-modal>
    <div class="w-full max-w-md rounded-lg bg-background shadow-xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="editAdjustmentModalTitle" class="text-lg font-semibold text-foreground">Edit Adjustment</h2>
            <button type="button" data-close-modal="editAdjustmentModal"
                class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="px-6 py-5">
            <form id="editAdjustmentForm" data-ledger-adjustment-edit-form
                method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="editAdjustmentRecordedAt" :value="'Transaction Date'" />
                    <p id="editAdjustmentRecordedAt_help" class="mt-1 text-xs text-foreground/60">
                        When this transaction occurred. Backdate if needed.
                    </p>
                    <x-text-input id="editAdjustmentRecordedAt" name="recorded_at" type="date"
                        :max="now()->toDateString()" required
                        aria-describedby="editAdjustmentRecordedAt_help"
                        class="mt-1 block w-full" />
                    <x-input-error :messages="[]" data-error-for="recorded_at" class="mt-1 hidden" />
                </div>

                <div>
                    <x-input-label for="editAdjustmentAmount" :value="'Amount'" />
                    <p id="editAdjustmentAmount_help" class="mt-1 text-xs text-foreground/60">
                        Enter amount in USD (e.g. 100.00).
                    </p>
                    <x-text-input id="editAdjustmentAmount" name="amount" type="number"
                        step="0.01" min="0.01" max="999999.99" required
                        aria-describedby="editAdjustmentAmount_help"
                        class="mt-1 block w-full" />
                    <x-input-error :messages="[]" data-error-for="amount" class="mt-1 hidden" />
                </div>

                <div>
                    <x-input-label for="editAdjustmentNotes" :value="'Notes (optional)'" />
                    <textarea id="editAdjustmentNotes" name="notes" rows="3" maxlength="500"
                        class="mt-1 block w-full border border-input bg-white text-foreground rounded-base shadow-sm focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>
                    <x-input-error :messages="[]" data-error-for="notes" class="mt-1 hidden" />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" data-close-modal="editAdjustmentModal"
                        class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
