<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::page-header title="Accounts Ledger" subtitle="View all customer and therapist account balances">
    </x-ui::page-header>

    {{-- Account type toggle --}}
    <x-ui::card class="p-4 mb-6">
        <div class="inline-flex rounded-md bg-background/subtle p-1">
            <a href="{{ route('admin.ledger.accounts.index', ['type' => 'schools']) }}"
                class="px-4 py-2 text-sm font-medium rounded-md {{ $accountType === 'schools' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background' }}">
                School Accounts (AR)
            </a>
            <a href="{{ route('admin.ledger.accounts.index', ['type' => 'therapists']) }}"
                class="ml-1 px-4 py-2 text-sm font-medium rounded-md {{ $accountType === 'therapists' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background' }}">
                Therapist Accounts (AP)
            </a>
            <a href="{{ route('admin.ledger.accounts.index', ['type' => 'all-transactions']) }}"
                class="ml-1 px-4 py-2 text-sm font-medium rounded-md {{ $accountType === 'all-transactions' ? 'bg-primary text-primary-foreground' : 'text-foreground hover:bg-background' }}">
                All Transactions
            </a>
        </div>
    </x-ui::card>

    @if ($accountType === 'all-transactions')
        {{-- All Transactions Tab --}}
        <x-ui::card class="p-6 space-y-4 overflow-hidden">
            <h2 class="text-sm font-semibold text-foreground">All Transactions</h2>
            @include('admin.ledger.accounts._all_transactions_table', [
                'allTransactionsDatatableUrl' => $allTransactionsDatatableUrl,
                'schools' => $schools,
                'therapists' => $therapists,
            ])
        </x-ui::card>
    @else
    {{-- Summary --}}
    @if (!empty($summary))
    <x-ui::card class="p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-foreground/70">Total Accounts</p>
                <p class="text-3xl font-semibold mt-1">{{ $summary['total_accounts'] ?? 0 }}</p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">
                    Total {{ $accountType === 'schools' ? 'Invoiced' : 'Billed' }}
                </p>
                <p class="text-3xl font-semibold mt-1">
                    ${{ number_format($summary['total_invoiced_or_billed'] ?? 0, 2) }}
                </p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Total Paid</p>
                <p class="text-3xl font-semibold mt-1 text-success">
                    ${{ number_format($summary['total_paid'] ?? 0, 2) }}
                </p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Total Outstanding</p>
                <p class="text-3xl font-semibold mt-1 {{ ($summary['total_outstanding'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">
                    ${{ number_format($summary['total_outstanding'] ?? 0, 2) }}
                </p>
            </div>
        </div>
    </x-ui::card>
    @endif

    {{-- Accounts Table --}}
    <x-ui::card class="p-6 space-y-4 overflow-hidden">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-foreground">
                {{ $accountType === 'schools' ? 'School Accounts' : 'Therapist Accounts' }}
            </h2>
            <a href="{{ route('admin.ledger.accounts.export', ['type' => $accountType]) }}"
                class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                Export
            </a>
        </div>
        @if (isset($datatableUrl))
            <div class="overflow-x-auto">
                <table id="ledgerAccountsTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}" data-filter-type="{{ $accountType }}">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'School/Family' : 'Therapist' }}
                            </th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Contact</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'Invoiced' : 'Billed' }}
                            </th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Paid</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                            <th class="text-center py-3 px-4 text-sm font-medium text-foreground">Transactions</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        @elseif (isset($accounts) && $accounts->count() > 0)
            <div class="overflow-x-auto">
                <table id="ledgerAccountsTable" class="w-full display">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'School/Family' : 'Therapist' }}
                            </th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Contact</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'Invoiced' : 'Billed' }}
                            </th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Paid</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                            <th class="text-center py-3 px-4 text-sm font-medium text-foreground">Transactions</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">
                                    <div class="font-medium">
                                        @if ($accountType === 'schools')
                                            <a href="{{ route('admin.schools.show', $account->id) }}"
                                                class="text-primary hover:underline">
                                                {{ $account->name }}
                                            </a>
                                        @else
                                            <a href="{{ route('admin.therapists.show', $account->id) }}"
                                                class="text-primary hover:underline">
                                                {{ $account->name }}
                                            </a>
                                        @endif
                                    </div>
                                    <div class="text-xs text-foreground/60">
                                        Member since {{ $account->created_at->format('M Y') }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @if ($account->email ?? $account->contact_email ?? null)
                                        <div class="text-foreground">{{ $account->contact_email ?? $account->email }}</div>
                                    @endif
                                    @if ($account->contact_phone ?? null)
                                        <div class="text-foreground/80 text-xs">{{ $account->contact_phone }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div
                                        class="font-semibold {{ $accountType === 'schools' ? 'text-success-600' : 'text-danger-600' }}">
                                        ${{ number_format($accountType === 'schools' ? $account->total_invoiced : $account->total_billed, 2) }}
                                    </div>
                                    <div class="text-xs text-foreground/60">
                                        {{ $accountType === 'schools' ? $account->invoices_count : $account->bills_count }}
                                        {{ $accountType === 'schools' ? 'invoice(s)' : 'bill(s)' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <span class="font-semibold text-info-600">
                                        ${{ number_format($account->total_paid, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <span
                                        class="font-semibold {{ $account->current_balance >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                        ${{ number_format(abs($account->current_balance), 2) }}
                                        {{ $account->current_balance < 0 ? 'DR' : 'CR' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-secondary/10 text-secondary-700">
                                        {{ $account->transaction_count }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end">
                                        <a href="{{ route('admin.ledger.accounts.show', ['type' => $accountType === 'schools' ? 'school' : 'therapist', 'id' => $account->id]) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="View Ledger"
                                            aria-label="View ledger for {{ $account->name }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui::empty-state title="No accounts found"
                description="No accounts match your current filters. Try adjusting your search criteria." />
        @endif
    </x-ui::card>
    @endif {{-- end all-transactions else --}}

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ledger-accounts-index.js'])
    </x-slot>
</x-admin.layouts.app>
