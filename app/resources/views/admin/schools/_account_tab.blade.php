@php
    use App\Support\Currency;

    $netBalance = (float) $accountSummary['net_balance'];
    $balanceColor = $netBalance > 0 ? 'text-danger' : ($netBalance < 0 ? 'text-success' : 'text-foreground');
    $balanceSuffix = $netBalance > 0 ? 'DR' : ($netBalance < 0 ? 'CR' : '');
    $summaryItems = [
        [
            'label' => 'Total Invoiced',
            'value' => Currency::format((float) $accountSummary['total_invoiced']),
            'value_class' => 'text-danger',
        ],
        [
            'label' => 'Total Paid',
            'value' => Currency::format((float) $accountSummary['total_paid']),
            'value_class' => 'text-success',
        ],
        [
            'label' => 'Session Charges',
            'value' => Currency::format((float) $accountSummary['total_charges']),
            'value_class' => 'text-danger',
        ],
        [
            'label' => 'Credit Notes',
            'value' => Currency::format((float) $accountSummary['total_credit_notes']),
            'value_class' => 'text-success',
        ],
        [
            'label' => 'Refunds',
            'value' => Currency::format((float) $accountSummary['total_refunds']),
            'value_class' => 'text-danger',
        ],
    ];
@endphp

<div class="space-y-4">
    {{-- Single inline summary strip: all-time totals + current balance --}}
    <x-ui::card class="px-6 py-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach ($summaryItems as $item)
                <div>
                    <p class="text-xs text-foreground/60">{{ $item['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold {{ $item['value_class'] }} whitespace-nowrap">
                        {{ $item['value'] }}
                    </p>
                </div>
            @endforeach
            <div>
                <div class="flex items-center gap-1">
                    <p class="text-xs font-medium text-foreground/70">Current Balance</p>
                    <x-ui::tooltip-icon
                        content="Computed from approved billable lessons plus payments, credit notes, and refunds. Will not match the canonical ledger balance when invoices cover sessions that aren't yet approved." />
                </div>
                <p class="mt-1 text-lg font-semibold {{ $balanceColor }} whitespace-nowrap">
                    {{ Currency::formatAbs($netBalance) }}
                    @if ($balanceSuffix !== '')
                        <span class="text-xs font-medium text-foreground/60">{{ $balanceSuffix }}</span>
                    @endif
                </p>
            </div>
        </div>
    </x-ui::card>

    {{-- Transactions table --}}
    <x-ui::card class="p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h3 class="text-sm font-semibold text-foreground">Transactions</h3>

            <form id="schoolAccountFilters" class="flex flex-wrap items-center gap-2">
                <div class="w-40 shrink-0">
                    <x-ui::input id="filter_date_from" type="date" name="filter_date_from"
                        aria-label="From date" value="{{ $accountDefaultFrom }}" />
                </div>
                <span class="text-xs text-foreground/60 shrink-0">to</span>
                <div class="w-40 shrink-0">
                    <x-ui::input id="filter_date_to" type="date" name="filter_date_to"
                        aria-label="To date" value="{{ $accountDefaultTo }}" />
                </div>
                <x-ui::button type="submit" class="shrink-0">Apply filters</x-ui::button>
                <x-ui::button type="button" variant="secondary" id="schoolAccountResetFilters" class="shrink-0">
                    Reset
                </x-ui::button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table id="schoolAccountTable"
                class="w-full border-collapse school-account-table display"
                data-datatable-url="{{ $datatableUrl }}"
data-default-from="{{ $accountDefaultFrom }}"
                data-default-to="{{ $accountDefaultTo }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Student</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Description</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

</div>
