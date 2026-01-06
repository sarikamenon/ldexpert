<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapist-bills-show.js'])
    </x-slot>

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
                <a href="{{ route('admin.billing.therapist-bills.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                    Back to List
                </a>
                <a href="{{ route('admin.billing.therapist-bills.download', $bill) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Download PDF
                </a>
                @if ($bill->isDraft())
                    <form method="POST" action="{{ route('admin.billing.therapist-bills.send', $bill) }}"
                        class="inline" id="sendBillForm">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium">
                            Send Bill
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-billing.therapist-bill-detail :bill="$bill" />
</x-admin.layouts.app>
