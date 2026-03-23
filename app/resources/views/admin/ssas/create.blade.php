<x-admin.layouts.app>
    <x-page-title title="Create SSA" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the highlighted errors and try again.
        </x-ui::alert>
    @endif

    @include('admin.ssas._form')

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ssas-form.js'])
    </x-slot>
</x-admin.layouts.app>

