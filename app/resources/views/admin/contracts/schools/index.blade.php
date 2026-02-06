<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="School Contracts" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-admin.school-contracts-list :contracts="$contracts" :filters="$filters" :statuses="$statuses" :schools="$schools"
        :metrics="$metrics" :show-metrics="true" context="index" />

    @vite(['resources/js/pages/admin-contracts-schools-index.js'])
</x-admin.layouts.app>
