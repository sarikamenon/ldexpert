<x-admin.layouts.app>
    <x-ui::page-header title="Therapist Bill Payments" subtitle="Review payments made to therapists">
    </x-ui::page-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.payments.therapist-bills.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="from_date">
                        From Date
                    </label>
                    <input type="date" id="from_date" name="from_date" value="{{ request('from_date') }}"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="to_date">
                        To Date
                    </label>
                    <input type="date" id="to_date" name="to_date" value="{{ request('to_date') }}"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="method">
                        Payment Method
                    </label>
                    <select id="method" name="method"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        <option value="">All Methods</option>
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}" @selected(request('method') === $method->value)>
                                {{ $method->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="search">
                        Search
                    </label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                        placeholder="Reference or Therapist"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Apply Filters
                </button>
                <a href="{{ route('admin.payments.therapist-bills.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Clear Filters
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Summary --}}
    @if ($payments->count() > 0)
        <x-ui::card class="p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Total Payments</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($totalAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-foreground/70">Number of Payments</p>
                    <p class="text-2xl font-bold mt-1">{{ $payments->total() }}</p>
                </div>
            </div>
        </x-ui::card>
    @endif

    {{-- Payments List --}}
    <x-ui::card class="overflow-hidden">
        @if ($payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Therapist</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Bills</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Amount</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Method</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Reference</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Recorded By</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->paid_at?->format('M d, Y') ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @php
                                        $firstAllocation = $payment->allocations->first();
                                        $therapist = $firstAllocation?->therapistBill?->therapist;
                                    @endphp
                                    @if ($therapist)
                                        <a href="{{ route('admin.therapists.show', $therapist) }}"
                                            class="text-primary hover:underline">
                                            {{ $therapist->name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @if ($payment->allocations->count() === 1 && $firstAllocation?->therapistBill)
                                        <a href="{{ route('admin.billing.therapist-bills.show', $firstAllocation->therapistBill) }}"
                                            class="text-primary hover:underline font-medium">
                                            #{{ $firstAllocation->therapistBill->bill_number }}
                                        </a>
                                    @elseif ($payment->allocations->count() > 1)
                                        <span class="text-sm text-foreground/70">
                                            {{ $payment->allocations->count() }} bills
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm text-right font-medium text-danger-600">
                                    ${{ number_format($payment->amount, 2) }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ $payment->method->label() }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->reference ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    {{ $payment->recordedBy->name ?? 'System' }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    @if ($payment->allocations->count() === 1 && $firstAllocation?->therapistBill)
                                        <div class="flex items-center justify-end">
                                            <a href="{{ route('admin.billing.therapist-bills.show', $firstAllocation->therapistBill) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                title="View Bill"
                                                aria-label="View bill {{ $firstAllocation->therapistBill->bill_number }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-xs text-foreground/60">View via Accounts Ledger</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-border">
                {{ $payments->links() }}
            </div>
        @else
            <x-ui::empty-state title="No bill payments found"
                description="No bill payments match your current filters. Try adjusting your search criteria." />
        @endif
    </x-ui::card>
</x-admin.layouts.app>
