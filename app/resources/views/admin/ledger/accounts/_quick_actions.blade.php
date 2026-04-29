{{-- Quick action buttons under the transactions table. --}}
<x-ui::card class="mt-6 p-4">
    <h5 class="text-sm font-semibold mb-3">Quick Actions</h5>
    <div class="flex flex-wrap gap-3">
        @if ($type === 'school')
            <a href="{{ route('admin.schools.show', $account) }}"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                View School/Family Profile
            </a>
            <a href="{{ route('admin.invoices.index', ['school_id' => $account->id]) }}"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                View All Invoices
            </a>
            <a href="{{ route('admin.invoices.create', ['school_id' => $account->id]) }}"
                class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90">
                + New Invoice
            </a>
        @else
            <a href="{{ route('admin.therapists.show', $account) }}"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                View Therapist Profile
            </a>
            <a href="{{ route('admin.billing.therapist-bills.index', ['therapist_id' => $account->id]) }}"
                class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle">
                View All Bills
            </a>
            <a href="{{ route('admin.billing.therapist-bills.create', ['therapist_id' => $account->id]) }}"
                class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm hover:bg-primary/90">
                + New Bill
            </a>
        @endif

        <button type="button" data-open-modal="creditNoteModal"
            class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            + Credit Note
        </button>
        <button type="button" data-open-modal="refundModal"
            class="px-4 py-2 border border-border rounded-md text-sm hover:bg-background/subtle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            + Refund
        </button>
    </div>
</x-ui::card>
