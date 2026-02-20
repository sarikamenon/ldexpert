<x-admin.layouts.app>
    <x-ui::page-header title="Finance Dashboard" subtitle="Financial Overview (all-time totals)" />

    {{-- Quick links --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('admin.invoices.index') }}"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-foreground/80 bg-muted/50 rounded-md hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Invoices
        </a>
        <a href="{{ route('admin.billing.therapist-bills.index') }}"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-foreground/80 bg-muted/50 rounded-md hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Therapist Bills
        </a>
        <a href="{{ route('admin.expenses.index') }}"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-foreground/80 bg-muted/50 rounded-md hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Expenses
        </a>
        <a href="{{ route('admin.payments.invoices.index') }}"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-foreground/80 bg-muted/50 rounded-md hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Payments Received
        </a>
        <a href="{{ route('admin.payments.therapist-bills.index') }}"
            class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-foreground/80 bg-muted/50 rounded-md hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring transition-colors">
            Payments Made
        </a>
    </div>

    {{-- Key Metrics - All-time --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <a href="{{ route('admin.payments.invoices.index') }}"
            class="block p-6 bg-white border border-border rounded-lg shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="View payments received">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Revenue Collected (all-time)</p>
                    <p class="text-2xl font-bold mt-1 text-success">${{ number_format($revenueCollected, 2) }}</p>
                </div>
                <div class="p-3 bg-success/10 rounded-full">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.payments.therapist-bills.index') }}"
            class="block p-6 bg-white border border-border rounded-lg shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="View payments made to therapists">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Paid to Therapists (all-time)</p>
                    <p class="text-2xl font-bold mt-1 text-danger">${{ number_format($therapistPayments, 2) }}</p>
                </div>
                <div class="p-3 bg-danger/10 rounded-full">
                    <svg class="w-6 h-6 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </a>

        <a href="{{ route('admin.expenses.index') }}"
            class="block p-6 bg-white border border-border rounded-lg shadow-sm hover:shadow-md transition-shadow focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="View expenses">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Total Expenses (all-time)</p>
                    <p class="text-2xl font-bold mt-1 text-danger">${{ number_format($totalExpenses, 2) }}</p>
                </div>
                <div class="p-3 bg-danger/10 rounded-full">
                    <svg class="w-6 h-6 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </a>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Net Income (all-time)</p>
                    <p class="text-2xl font-bold mt-1 {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($netIncome, 2) }}</p>
                </div>
                <div class="p-3 {{ $netIncome >= 0 ? 'bg-success/10' : 'bg-danger/10' }} rounded-full">
                    <svg class="w-6 h-6 {{ $netIncome >= 0 ? 'text-success' : 'text-danger' }}" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
            </div>
        </x-ui::card>
    </div>

    {{-- AR/AP Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Accounts Receivable</h3>
                <a href="{{ route('admin.invoices.index') }}"
                    class="flex-shrink-0 text-sm text-primary hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring rounded">
                    View All Invoices →
                </a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-foreground/70">Outstanding Balance</span>
                    <span class="text-lg font-semibold">${{ number_format($arTotal, 2) }}</span>
                </div>
                @if ($overdueInvoicesCount > 0)
                    <div class="flex justify-between items-center rounded-md bg-danger/10 px-3 py-2 border border-danger/20">
                        <span class="text-sm font-medium text-foreground">Overdue Invoices</span>
                        <span class="min-w-[1.75rem] text-center rounded-full px-2.5 py-1 text-sm font-bold bg-danger text-white">{{ $overdueInvoicesCount }}</span>
                    </div>
                @endif
            </div>
        </x-ui::card>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Accounts Payable</h3>
                <a href="{{ route('admin.billing.therapist-bills.index') }}"
                    class="flex-shrink-0 text-sm text-primary hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring rounded">
                    View All Bills →
                </a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-foreground/70">Outstanding Balance</span>
                    <span class="text-lg font-semibold">${{ number_format($apTotal, 2) }}</span>
                </div>
                @if ($overdueBillsCount > 0)
                    <div class="flex justify-between items-center rounded-md bg-danger/10 px-3 py-2 border border-danger/20">
                        <span class="text-sm font-medium text-foreground">Overdue Bills</span>
                        <span class="min-w-[1.75rem] text-center rounded-full px-2.5 py-1 text-sm font-bold bg-danger text-white">{{ $overdueBillsCount }}</span>
                    </div>
                @endif
            </div>
        </x-ui::card>
    </div>

    {{-- Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Payments Received --}}
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Recent Payments Received</h3>
                <a href="{{ route('admin.payments.invoices.index') }}"
                    class="flex-shrink-0 text-sm text-primary hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring rounded">
                    View All →
                </a>
            </div>
            @if ($recentPaymentsReceived->count() > 0)
                <div class="space-y-3">
                    @foreach ($recentPaymentsReceived as $payment)
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium">{{ $payment->school?->name ?? 'Unknown school' }}</p>
                                <p class="text-xs text-foreground/70">{{ $payment->paid_at->format('M d, Y') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-success">${{ number_format($payment->amount, 2) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui::empty-state title="No recent payments"
                    description="Payments received from schools will appear here."
                    actionLabel="View invoice payments"
                    actionHref="{{ route('admin.payments.invoices.index') }}" />
            @endif
        </x-ui::card>

        {{-- Recent Payments Made --}}
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Recent Payments Made</h3>
                <a href="{{ route('admin.payments.therapist-bills.index') }}"
                    class="flex-shrink-0 text-sm text-primary hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring rounded">
                    View All →
                </a>
            </div>
            @if ($recentPaymentsMade->count() > 0)
                <div class="space-y-3">
                    @foreach ($recentPaymentsMade as $payment)
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium">{{ $payment->therapist?->name ?? 'Unknown therapist' }}</p>
                                <p class="text-xs text-foreground/70">{{ $payment->paid_at->format('M d, Y') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-danger">${{ number_format($payment->amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui::empty-state title="No recent payments"
                    description="Payments made to therapists will appear here."
                    actionLabel="View bill payments"
                    actionHref="{{ route('admin.payments.therapist-bills.index') }}" />
            @endif
        </x-ui::card>

        {{-- Recent Expenses --}}
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Recent Expenses</h3>
                <a href="{{ route('admin.expenses.index') }}"
                    class="flex-shrink-0 text-sm text-primary hover:text-primary/80 focus:outline-none focus:ring-2 focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring rounded">
                    View All →
                </a>
            </div>
            @if ($recentExpenses->count() > 0)
                <div class="space-y-3">
                    @foreach ($recentExpenses as $expense)
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium">{{ $expense->category->name }}</p>
                                <p class="text-xs text-foreground/70">{{ $expense->expense_date->format('M d, Y') }}
                                </p>
                            </div>
                            <p class="text-sm font-semibold">${{ number_format($expense->amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <x-ui::empty-state title="No recent expenses"
                    description="Expenses will appear here."
                    actionLabel="View expenses"
                    actionHref="{{ route('admin.expenses.index') }}" />
            @endif
        </x-ui::card>
    </div>
</x-admin.layouts.app>
