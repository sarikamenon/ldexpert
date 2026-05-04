<x-admin.layouts.app>
    @push('styles')
        @vite('resources/js/pages/admin-schools-form.js')
    @endpush

    <x-page-title title="Edit School/Family" />

    @include('admin.schools._form')
</x-admin.layouts.app>
