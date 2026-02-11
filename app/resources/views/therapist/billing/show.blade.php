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
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            Download PDF
                        </a>
                    </div>
                </div>
            </div>

            <x-billing.therapist-bill-detail :bill="$bill" />
        </div>
    </div>
</x-app-layout>
