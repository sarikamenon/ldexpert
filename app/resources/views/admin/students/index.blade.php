<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Students" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-admin.students-list :students="$students"
        :filters="$filters"
        :schools="$schools"
        :statuses="$statuses"
        :showMetrics="true"
        :metrics="$metrics"
        context="index"
        :datatable-url="$datatableUrl" />

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-students-index.js'])
    </x-slot>
</x-admin.layouts.app>
