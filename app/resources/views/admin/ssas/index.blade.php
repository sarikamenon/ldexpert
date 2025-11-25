<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-admin.ssas-list :ssas="$ssas" :filters="$filters" :statuses="$statuses" :students="$students" :therapists="$therapists"
        :services="$services" :showMetrics="true" :metrics="$metrics" context="index" />

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ssas-index.js'])
    </x-slot>
</x-admin.layouts.app>
