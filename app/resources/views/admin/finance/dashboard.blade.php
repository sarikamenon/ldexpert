<x-admin.layouts.app>
    <x-ui::page-header title="Finance Dashboard" :subtitle="'Financial Overview - ' . $currentMonth" />

    {{-- Key Metrics - This Month --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Revenue Invoiced</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($revenueInvoiced, 2) }}</p>
                </div>
                <div class="p-3 bg-primary/10 rounded-full">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Revenue Collected</p>
                    <p class="text-2xl font-bold mt-1 text-success">${{ number_format($revenueCollected, 2) }}</p>
                </div>
                <div class="p-3 bg-success/10 rounded-full">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Total Expenses</p>
                    <p class="text-2xl font-bold mt-1 text-danger">${{ number_format($totalExpenses, 2) }}</p>
                </div>
                <div class="p-3 bg-danger/10 rounded-full">
                    <svg class="w-6 h-6 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Net Income</p>
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
                <a href="{{ route('admin.invoices.index') }}" class="text-sm text-primary hover:text-primary/80">
                    View All Invoices →
                </a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-foreground/70">Outstanding Balance</span>
                    <span class="text-lg font-semibold">${{ number_format($arTotal, 2) }}</span>
                </div>
                @if ($overdueInvoicesCount > 0)
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-foreground/70">Overdue Invoices</span>
                        <span class="text-lg font-semibold text-warning">{{ $overdueInvoicesCount }}</span>
                    </div>
                @endif
            </div>
        </x-ui::card>

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Accounts Payable</h3>
                <a href="{{ route('admin.billing.therapist-bills.index') }}"
                    class="text-sm text-primary hover:text-primary/80">
                    View All Bills →
                </a>
            </div>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-foreground/70">Outstanding Balance</span>
                    <span class="text-lg font-semibold">${{ number_format($apTotal, 2) }}</span>
                </div>
                @if ($overdueBillsCount > 0)
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-foreground/70">Overdue Bills</span>
                        <span class="text-lg font-semibold text-warning">{{ $overdueBillsCount }}</span>
                    </div>
                @endif
            </div>
        </x-ui::card>
    </div>

    {{-- Recent Activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Recent Payments Received --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Recent Payments Received</h3>
            @if ($recentPaymentsReceived->count() > 0)
                <div class="space-y-3">
                    @foreach ($recentPaymentsReceived as $payment)
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium">{{ $payment->invoice->school_name }}</p>
                                <p class="text-xs text-foreground/70">{{ $payment->paid_at->format('M d, Y') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-success">${{ number_format($payment->amount, 2) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-foreground/60">No recent payments</p>
            @endif
        </x-ui::card>

        {{-- Recent Payments Made --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Recent Payments Made</h3>
            @if ($recentPaymentsMade->count() > 0)
                <div class="space-y-3">
                    @foreach ($recentPaymentsMade as $payment)
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="text-sm font-medium">{{ $payment->therapistBill->therapist_name }}</p>
                                <p class="text-xs text-foreground/70">{{ $payment->paid_at->format('M d, Y') }}</p>
                            </div>
                            <p class="text-sm font-semibold text-danger">${{ number_format($payment->amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-foreground/60">No recent payments</p>
            @endif
        </x-ui::card>

        {{-- Recent Expenses --}}
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Recent Expenses</h3>
                <a href="{{ route('admin.expenses.index') }}" class="text-sm text-primary hover:text-primary/80">
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
                <p class="text-sm text-foreground/60">No recent expenses</p>
            @endif
        </x-ui::card>
    </div>
</x-admin.layouts.app>
