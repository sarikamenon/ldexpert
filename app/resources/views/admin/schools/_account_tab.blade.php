@php
    $netBalance = (float) ($accountSummary['net_balance'] ?? 0.0);
    $balanceColor = $netBalance > 0 ? 'text-danger' : ($netBalance < 0 ? 'text-success' : 'text-foreground');
    $balanceSuffix = $netBalance > 0 ? 'DR' : ($netBalance < 0 ? 'CR' : '');
    $summaryItems = [
        [
            'label' => 'Charges',
            'value' => '$' . number_format((float) ($accountSummary['total_charges'] ?? 0), 2),
            'value_class' => 'text-danger',
        ],
        [
            'label' => 'Payments',
            'value' => '$' . number_format((float) ($accountSummary['total_payments'] ?? 0), 2),
            'value_class' => 'text-success',
        ],
        [
            'label' => 'Credit Notes',
            'value' => '$' . number_format((float) ($accountSummary['total_credit_notes'] ?? 0), 2),
            'value_class' => 'text-success',
        ],
        [
            'label' => 'Refunds',
            'value' => '$' . number_format((float) ($accountSummary['total_refunds'] ?? 0), 2),
            'value_class' => 'text-danger',
        ],
        [
            'label' => 'Transactions',
            'value' => (string) ($accountSummary['transaction_count'] ?? 0),
            'value_class' => 'text-foreground',
        ],
    ];
@endphp

<div class="space-y-4">
    {{-- Single inline summary strip: balance + all-time totals --}}
    <x-ui::card class="px-6 py-4">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
            <div>
                <div class="flex items-center gap-1">
                    <p class="text-xs font-medium text-foreground/70">Current Balance</p>
                    <x-ui::tooltip-icon
                        content="Computed from approved billable lessons plus payments, credit notes, and refunds. Will not match the canonical ledger balance." />
                </div>
                <p class="mt-1 text-lg font-semibold {{ $balanceColor }} whitespace-nowrap">
                    ${{ number_format(abs($netBalance), 2) }}
                    @if ($balanceSuffix !== '')
                        <span class="text-xs font-medium text-foreground/60">{{ $balanceSuffix }}</span>
                    @endif
                </p>
            </div>
            @foreach ($summaryItems as $item)
                <div>
                    <p class="text-xs text-foreground/60">{{ $item['label'] }}</p>
                    <p class="mt-1 text-lg font-semibold {{ $item['value_class'] }} whitespace-nowrap">
                        {{ $item['value'] }}
                    </p>
                </div>
            @endforeach
        </div>
    </x-ui::card>

    {{-- Transactions table --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-sm font-semibold text-foreground">Transactions</h3>

        <div class="overflow-x-auto">
            <table id="schoolAccountTable"
                class="w-full border-collapse school-account-table display"
                data-datatable-url="{{ $datatableUrl }}"
                data-schedule-details-url="{{ $scheduleDetailsUrl }}">
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

    {{-- Schedule details modal (reused from calendar) --}}
    <x-schedule.schedule-details-modal />
</div>
