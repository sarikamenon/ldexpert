<x-admin.layouts.app>
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
        </div>
    </x-ui::card>

    {{-- Search --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.ledger.accounts.index') }}" class="space-y-4">
            <input type="hidden" name="type" value="{{ $accountType }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="search">
                        Search Accounts
                    </label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                        placeholder="Search by name or email"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Search
                </button>
                <a href="{{ route('admin.ledger.accounts.index', ['type' => $accountType]) }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Clear
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Summary --}}
    <x-ui::card class="p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-foreground/70">Total Accounts</p>
                <p class="text-2xl font-bold mt-1">{{ $accounts->count() }}</p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">
                    Total {{ $accountType === 'schools' ? 'Invoiced' : 'Billed' }}
                </p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($accounts->sum($accountType === 'schools' ? 'total_invoiced' : 'total_billed'), 2) }}
                </p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Total Paid</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($accounts->sum('total_paid'), 2) }}
                </p>
            </div>
            <div>
                <p class="text-sm text-foreground/70">Total Outstanding</p>
                <p class="text-2xl font-bold mt-1">
                    ${{ number_format($accounts->sum('outstanding'), 2) }}
                </p>
            </div>
        </div>
    </x-ui::card>

    {{-- Accounts Table --}}
    <x-ui::card class="overflow-hidden">
        @if ($accounts->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'School' : 'Therapist' }}
                            </th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Contact</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">
                                {{ $accountType === 'schools' ? 'Invoiced' : 'Billed' }}
                            </th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Paid</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Outstanding</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Balance</th>
                            <th class="text-center py-3 px-4 text-sm font-medium text-foreground">Transactions</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">
                                    <div class="font-medium">{{ $account->name }}</div>
                                    <div class="text-xs text-foreground/60">
                                        Member since {{ $account->created_at->format('M Y') }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @if ($account->email)
                                        <div class="text-foreground">{{ $account->email }}</div>
                                    @endif
                                    @if ($account->phone)
                                        <div class="text-foreground/80 text-xs">{{ $account->phone }}</div>
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
                                    @if ($account->outstanding > 0)
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-warning/10 text-warning-700">
                                            ${{ number_format($account->outstanding, 2) }}
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-success/10 text-success-700">
                                            $0.00
                                        </span>
                                    @endif
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
                    <tfoot>
                        <tr class="border-t border-border bg-background/subtle text-sm font-semibold">
                            <td class="py-3 px-4" colspan="2">Totals</td>
                            <td class="py-3 px-4 text-right">
                                ${{ number_format($accounts->sum($accountType === 'schools' ? 'total_invoiced' : 'total_billed'), 2) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                ${{ number_format($accounts->sum('total_paid'), 2) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                ${{ number_format($accounts->sum('outstanding'), 2) }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <x-ui::empty-state title="No accounts found"
                description="No accounts match your current filters. Try adjusting your search criteria." />
        @endif
    </x-ui::card>
</x-admin.layouts.app>
