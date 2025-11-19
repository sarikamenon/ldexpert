<x-admin.layouts.app>
    <x-page-title title="Create School Contract" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the errors below.
        </x-ui::alert>
    @endif

    @include('admin.contracts.schools.partials.form', [
        'action' => route('admin.contracts.schools.store'),
        'method' => 'POST',
    ])

    @vite(['resources/js/pages/admin-contracts-schools-form.js'])
</x-admin.layouts.app>

