<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">Bill {{ $bill->bill_number }}</h1>
                        <p class="text-sm text-foreground/60 mt-1">Billing Period:
                            {{ $bill->billing_period_start->format('M d') }} -
                            {{ $bill->billing_period_end->format('M d, Y') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <x-ui::badge :variant="match ($bill->status) {
                            \App\Enums\TherapistBillStatus::DRAFT => 'secondary',
                            \App\Enums\TherapistBillStatus::SENT => 'primary',
                            \App\Enums\TherapistBillStatus::PAID => 'success',
                            default => 'secondary',
                        }">
                            {{ $bill->status?->label() }}
                        </x-ui::badge>
                        <a href="{{ route('therapist.billing.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                            Back to List
                        </a>
                        <a href="{{ route('therapist.billing.download', $bill) }}"
                            class="inline-flex items-center px-4 py-2 border border-primary text-primary rounded-lg hover:bg-primary/10 text-sm font-medium">
                            Download PDF
                        </a>
                    </div>
                </div>
            </div>

            <x-ui::card class="p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">Bill To</h3>
                        <div class="text-sm text-foreground">
                            <p class="font-medium">{{ $bill->therapist_name }}</p>
                            @if ($bill->therapist_address)
                                <p class="mt-1">{{ $bill->therapist_address }}</p>
                            @endif
                            @if ($bill->therapist_email)
                                <p class="mt-1">{{ $bill->therapist_email }}</p>
                            @endif
                            @if ($bill->therapist_phone)
                                <p class="mt-1">{{ $bill->therapist_phone }}</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">From</h3>
                        <div class="text-sm text-foreground">
                            <p class="font-medium">{{ $bill->company_name }}</p>
                            @if ($bill->company_address)
                                <p class="mt-1">{{ $bill->company_address }}</p>
                            @endif
                            @if ($bill->company_phone)
                                <p>{{ $bill->company_phone }}</p>
                            @endif
                            @if ($bill->company_email)
                                <p class="mt-1">{{ $bill->company_email }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-border">
                    <div>
                        <p class="text-sm text-foreground/70">Bill Date</p>
                        <p class="text-sm font-medium mt-1">{{ $bill->bill_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-foreground/70">Due Date</p>
                        <p class="text-sm font-medium mt-1">{{ $bill->due_date->format('M d, Y') }}</p>
                    </div>
                    @if ($bill->sent_at)
                        <div>
                            <p class="text-sm text-foreground/70">Sent At</p>
                            <p class="text-sm font-medium mt-1">{{ $bill->sent_at->format('M d, Y h:i A') }}</p>
                        </div>
                    @endif
                </div>
            </x-ui::card>

            <x-ui::card class="p-6">
                <h2 class="text-lg font-semibold text-foreground mb-4">Line Items</h2>

                @if ($bill->sessionLogs->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">School</th>
                                    <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                                    <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Rate</th>
                                    <th class="text-right py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bill->sessionLogs as $log)
                                    <tr class="border-b border-border hover:bg-background/subtle">
                                        <td class="py-3 px-4 text-sm">{{ $log->session_date->format('M d, Y') }}</td>
                                        <td class="py-3 px-4 text-sm">{{ $log->student->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-sm">{{ $log->service->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-sm">{{ $log->school->display_name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-sm">{{ $log->duration_minutes }} min</td>
                                        <td class="py-3 px-4 text-sm text-right">
                                            ${{ number_format($log->therapist_rate_amount ?? 0, 2) }}</td>
                                        <td class="py-3 px-4 text-sm text-right font-medium">
                                            ${{ number_format($log->therapist_billable_amount ?? 0, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-border">
                                    <td colspan="6"
                                        class="py-3 px-4 text-right text-sm font-medium text-foreground/70">
                                        Subtotal:</td>
                                    <td class="py-3 px-4 text-right text-sm font-medium">
                                        ${{ number_format($bill->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6"
                                        class="py-3 px-4 text-right text-sm font-medium text-foreground/70">Adjustments:
                                    </td>
                                    <td class="py-3 px-4 text-right text-sm font-medium">
                                        ${{ number_format($bill->adjustments_total, 2) }}</td>
                                </tr>
                                <tr class="bg-background/subtle">
                                    <td colspan="6" class="py-3 px-4 text-right text-lg font-semibold">Total Due:
                                    </td>
                                    <td class="py-3 px-4 text-right text-lg font-semibold">
                                        ${{ number_format($bill->total_due, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-foreground/60">No line items found.</p>
                @endif

                @if ($bill->notes)
                    <div class="mt-6 pt-6 border-t border-border">
                        <h3 class="text-sm font-medium text-foreground/70 mb-2">Notes</h3>
                        <p class="text-sm text-foreground/80">{{ $bill->notes }}</p>
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
