<x-admin.layouts.app>
    <x-page-title title="Add School" />

    <x-ui::card class="p-6">
        <form method="POST" action="{{ route('admin.schools.store') }}" class="space-y-6">
            @csrf
            @include('admin.schools._form', ['school' => null])

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.schools.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">Cancel</a>
                <button type="reset"
                    class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">Reset</button>
                <x-primary-button>Create School</x-primary-button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>
