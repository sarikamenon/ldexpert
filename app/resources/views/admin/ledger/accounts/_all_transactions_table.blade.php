{{-- Filters: Direction → School → Therapist → Date from → Date to --}}
@php
    $defaultDateFrom = now()->subDays(30)->format('Y-m-d');
    $defaultDateTo   = now()->format('Y-m-d');
@endphp

<div class="flex flex-wrap items-center gap-3 mb-4" id="allTransactionsFilters">
    <select id="atFilterDirection" name="filter_direction"
        class="w-40 px-3 py-1.5 text-sm border border-border rounded-md bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        aria-label="Direction">
        <option value="">All</option>
        <option value="income">Income</option>
        <option value="expense">Expense</option>
    </select>

    <select id="atFilterSchool" name="filter_school_id"
        class="px-3 py-1.5 text-sm border border-border rounded-md bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        aria-label="School / Family">
        <option value="">All Schools / Families</option>
        @foreach ($schools as $school)
            <option value="{{ $school->id }}">{{ $school->display_name ?? $school->full_name }}</option>
        @endforeach
    </select>

    <select id="atFilterTherapist" name="filter_therapist_id"
        class="px-3 py-1.5 text-sm border border-border rounded-md bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        aria-label="Therapist">
        <option value="">All Therapists</option>
        @foreach ($therapists as $therapist)
            <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
        @endforeach
    </select>

    <input type="date" id="atFilterDateFrom" name="filter_date_from"
        value="{{ $defaultDateFrom }}"
        class="px-3 py-1.5 text-sm border border-border rounded-md bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        aria-label="From date" />

    <input type="date" id="atFilterDateTo" name="filter_date_to"
        value="{{ $defaultDateTo }}"
        class="px-3 py-1.5 text-sm border border-border rounded-md bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        aria-label="To date" />

    <button id="atFilterApply" type="button"
        class="px-4 py-1.5 text-sm font-medium bg-primary text-primary-foreground rounded-md hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        Filter
    </button>

    <button id="atFilterReset" type="button"
        class="px-4 py-1.5 text-sm font-medium border border-border rounded-md text-foreground hover:bg-background/subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        Reset
    </button>

    <a id="atExportBtn"
        href="{{ route('admin.ledger.accounts.all-transactions.export') }}"
        class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium border border-border rounded-md text-foreground hover:bg-background/subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
        </svg>
        Export CSV
    </a>
</div>

{{-- Table --}}
<div class="overflow-x-auto">
    <table id="allTransactionsTable"
        class="w-full border-collapse display"
        data-datatable-url="{{ $allTransactionsDatatableUrl }}"
        data-default-date-from="{{ $defaultDateFrom }}"
        data-default-date-to="{{ $defaultDateTo }}">
        <thead class="bg-background/subtle">
            <tr>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground w-28">Date</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground w-32">Type</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground w-52">Account</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-foreground w-24">Debit</th>
                <th class="text-right py-3 px-4 text-sm font-medium text-foreground w-24">Credit</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Notes</th>
                <th class="text-left py-3 px-4 text-sm font-medium text-foreground w-28">Recorded By</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
