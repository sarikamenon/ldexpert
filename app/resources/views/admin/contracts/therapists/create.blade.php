<x-admin.layouts.app>
    <x-page-title title="Create Therapist Contract" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the errors below.
        </x-ui::alert>
    @endif

    @include('admin.contracts.therapists.partials.form', [
        'action' => route('admin.contracts.therapists.store'),
        'method' => 'POST',
    ])

    @vite(['resources/js/pages/admin-contracts-therapists-form.js'])
</x-admin.layouts.app>

