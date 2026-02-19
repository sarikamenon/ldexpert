<x-admin.layouts.app>
    <x-page-title title="Create Position" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the highlighted errors and try again.
        </x-ui::alert>
    @endif

    @include('admin.positions._form')
</x-admin.layouts.app>
