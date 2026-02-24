<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>
    <x-page-title title="Therapists" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-admin.therapists-list :therapists="$therapists" :filters="$filters" :positions="$positions" :showMetrics="true" :metrics="$metrics"
        :datatable-url="$datatableUrl" context="index" />

    <form method="POST" id="therapistStatusForm" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" id="statusInput">
        <input type="hidden" name="reason" id="statusReasonInput">
    </form>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapists-index.js'])
    </x-slot>
</x-admin.layouts.app>
