<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::show-header :title="$accountName" :subtitle="$accountType . ' Account Ledger'"
        :back-url="route('admin.ledger.accounts.index', ['type' => $type === 'school' ? 'schools' : 'therapists'])"
        back-label="Back to Accounts" />

    {{-- Summary --}}
    <div id="ledgerAccountStats" class="mb-6"
        data-stats-url="{{ route('admin.ledger.accounts.stats', ['type' => $type, 'id' => $id]) }}">
        @include('admin.ledger.accounts._stats', ['stats' => $stats, 'type' => $type])
    </div>

    {{-- Ledger entries --}}
    <x-ui::card class="p-6 space-y-4">
        <h5 class="text-sm font-semibold text-foreground">Transaction History</h5>

        @include('admin.ledger.accounts._transactions_table', [
            'datatableUrl' => $datatableUrl,
            'datatableFilterType' => $datatableFilterType ?? $type,
            'datatableFilterId' => $datatableFilterId ?? $id,
            'type' => $type,
            'id' => $id,
        ])
    </x-ui::card>

    @include('admin.ledger.accounts._quick_actions', ['type' => $type, 'account' => $account])

    @include('admin.ledger.accounts._adjustment_modals', ['type' => $type, 'account' => $account])

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ledger-accounts-show.js'])
    </x-slot>
</x-admin.layouts.app>
