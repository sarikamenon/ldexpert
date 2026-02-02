<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Therapist Contracts" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-admin.therapist-contracts-list :contracts="$contracts" :filters="$filters" :statuses="$statuses"
        :therapists="$therapists" :metrics="$metrics" :show-metrics="true" context="index" />

    @vite(['resources/js/pages/admin-contracts-therapists-index.js'])
</x-admin.layouts.app>
