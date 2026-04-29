{{-- Server-side ledger transactions table. Rows are rendered by LedgerEntryRowTransformer. --}}
<div class="overflow-x-auto">
    <table id="ledgerTransactionsTable"
        class="w-full border-collapse ledger-transactions-table display"
        data-datatable-url="{{ $datatableUrl }}"
        data-filter-type="{{ $datatableFilterType ?? $type }}"
        data-filter-id="{{ $datatableFilterId ?? $id }}">
        <thead class="bg-background/subtle">
            <tr>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Type</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Notes</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
