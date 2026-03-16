<x-admin.layouts.app>
    @push('styles')
        @vite('resources/js/pages/admin-students-form.js')
    @endpush

    <x-page-title title="Edit Student" />

    @include('admin.students._form')
</x-admin.layouts.app>
