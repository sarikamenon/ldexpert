@props([
    'type',
    'accountId',
    'transactionType',
    'formId',
])

@php
    $isCreditNote = $transactionType === 'credit_note';
    $submitLabel = $isCreditNote ? 'Record Credit Note' : 'Record Refund';
    $amountHelpId = $formId.'_amount_help';
    $notesHelpId = $formId.'_notes_help';
    $recordedAtHelpId = $formId.'_recorded_at_help';
    $today = now()->toDateString();
@endphp

<form id="{{ $formId }}"
    data-ledger-adjustment-form
    data-type="{{ $type }}"
    data-account-id="{{ $accountId }}"
    data-transaction-type="{{ $transactionType }}"
    action="{{ route('admin.ledger.accounts.adjustment.store', ['type' => $type, 'id' => $accountId]) }}"
    method="POST"
    class="space-y-4">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="account_id" value="{{ $accountId }}">
    <input type="hidden" name="transaction_type" value="{{ $transactionType }}">

    <div>
        <x-input-label :for="$formId.'_recorded_at'" :value="'Transaction Date'" />
        <p id="{{ $recordedAtHelpId }}" class="mt-1 text-xs text-foreground/60">
            When this transaction occurred. Backdate if needed.
        </p>
        <x-text-input
            :id="$formId.'_recorded_at'"
            name="recorded_at"
            type="date"
            :value="$today"
            :max="$today"
            required
            :aria-describedby="$recordedAtHelpId"
            class="mt-1 block w-full" />
        <x-input-error :messages="[]" data-error-for="recorded_at" class="mt-1 hidden" />
    </div>

    <div>
        <x-input-label :for="$formId.'_amount'" :value="'Amount'" />
        <p id="{{ $amountHelpId }}" class="mt-1 text-xs text-foreground/60">
            Enter amount in USD (e.g. 100.00).
        </p>
        <x-text-input
            :id="$formId.'_amount'"
            name="amount"
            type="number"
            step="0.01"
            min="0.01"
            max="999999.99"
            required
            :aria-describedby="$amountHelpId"
            class="mt-1 block w-full" />
        <x-input-error :messages="[]" data-error-for="amount" class="mt-1 hidden" />
    </div>

    <div>
        <x-input-label :for="$formId.'_notes'" :value="'Notes (optional)'" />
        <p id="{{ $notesHelpId }}" class="mt-1 text-xs text-foreground/60">
            Add a short reason or reference for this adjustment.
        </p>
        <textarea
            id="{{ $formId }}_notes"
            name="notes"
            rows="3"
            maxlength="500"
            aria-describedby="{{ $notesHelpId }}"
            class="mt-1 block w-full border border-input bg-white text-foreground rounded-base shadow-sm focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>
        <x-input-error :messages="[]" data-error-for="notes" class="mt-1 hidden" />
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <button type="button" data-ledger-adjustment-cancel
            class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Cancel
        </button>
        <button type="submit"
            class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            {{ $submitLabel }}
        </button>
    </div>
</form>
