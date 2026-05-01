{{-- School account tab: per-session charges merged with payments / credit notes / refunds. --}}
<div class="space-y-6">
    <x-ui::card class="p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-foreground/70">Current Balance</p>
                <p class="mt-1 text-2xl font-semibold {{ ($accountBalance ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                    ${{ number_format(abs((float) ($accountBalance ?? 0)), 2) }}{{ ($accountBalance ?? 0) > 0 ? ' DR' : (($accountBalance ?? 0) < 0 ? ' CR' : '') }}
                </p>
                <p class="mt-2 text-xs text-foreground/60">
                    Computed from per-session charges (paid therapist bills) plus payments, credit notes,
                    and refunds. Will not match the canonical ledger balance, which uses
                    invoice-level debits.
                </p>
            </div>
        </div>
    </x-ui::card>

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-sm font-semibold text-foreground">Account Activity</h3>

        <div class="overflow-x-auto">
            <table id="schoolAccountTable"
                class="w-full border-collapse school-account-table display"
                data-datatable-url="{{ $datatableUrl }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Student</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Description</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Debit</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Credit</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>
</div>
