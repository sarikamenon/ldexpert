<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <x-page-title title="Pending Schedule" />

            <x-ui::card class="p-6">
                <p class="text-foreground/70">Pending schedules will be displayed here.</p>
                {{-- TODO: Implement pending schedules list --}}
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
