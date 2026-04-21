<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapist-bills-show.js'])
    </x-slot>

    <x-ui::show-header :title="'Bill ' . $bill->bill_number"
        :subtitle="'Billing Period: ' . $bill->billing_period_start->format('M d') . ' - ' . $bill->billing_period_end->format('M d, Y')"
        :back-url="route('admin.billing.therapist-bills.index')" back-label="Back to List">
        <x-slot name="badge">
            <x-ui::badge :variant="match ($bill->status) {
                \App\Enums\TherapistBillStatus::DRAFT => 'secondary',
                \App\Enums\TherapistBillStatus::SENT => 'primary',
                \App\Enums\TherapistBillStatus::PAID => 'success',
                default => 'secondary',
            }">
                {{ $bill->status?->label() }}
            </x-ui::badge>
        </x-slot>
        <x-slot name="actions">
            @if (!$bill->isDraft() && !$bill->isPaid())
                <x-ui::button type="button" x-data=""
                    x-on:click="$dispatch('open-record-payment-modal')">
                    Record Payment
                </x-ui::button>
            @endif

            <a href="{{ route('admin.billing.therapist-bills.download', $bill) }}">
                <x-ui::button>
                    Download PDF
                </x-ui::button>
            </a>

            @if ($bill->isDraft())
                <a href="{{ route('admin.billing.therapist-bills.attach-sessions', $bill) }}">
                    <x-ui::button variant="secondary">
                        Add or remove sessions
                    </x-ui::button>
                </a>

                <form method="POST" action="{{ route('admin.billing.therapist-bills.send', $bill) }}"
                    class="inline" id="sendBillForm">
                    @csrf
                    <x-ui::button type="submit" variant="success">
                        Send Bill
                    </x-ui::button>
                </form>
            @endif

            @can('delete', $bill)
                <form method="POST" action="{{ route('admin.billing.therapist-bills.destroy', $bill) }}"
                    class="inline" id="deleteBillForm">
                    @csrf
                    @method('DELETE')
                    <x-ui::button type="submit" variant="danger" id="deleteBillBtn">
                        Delete Bill
                    </x-ui::button>
                </form>
            @endcan
        </x-slot>
    </x-ui::show-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-billing.therapist-bill-detail :bill="$bill" />
</x-admin.layouts.app>
