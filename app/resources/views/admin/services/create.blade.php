<x-admin.layouts.app>
    <x-page-title title="Create Service" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the highlighted errors and try again.
        </x-ui::alert>
    @endif

    @include('admin.services._form')

    <x-slot name="scripts">
        @vite('resources/js/pages/admin-services-form.js')
    </x-slot>
</x-admin.layouts.app>
