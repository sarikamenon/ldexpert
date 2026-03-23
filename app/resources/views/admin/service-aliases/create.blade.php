<x-admin.layouts.app>
    <x-page-title title="Create Service Alias" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the highlighted errors and try again.
        </x-ui::alert>
    @endif

    @include('admin.service-aliases._form')
</x-admin.layouts.app>
