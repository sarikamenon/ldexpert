<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">My Students</h1>
                <p class="text-sm text-foreground/60 mt-1">Students from assigned SSAs</p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-admin.students-list :students="$students" :filters="$filters" :statuses="$statuses" :showMetrics="false"
                context="therapist" />
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-students-index.js'])
    </x-slot>
</x-app-layout>