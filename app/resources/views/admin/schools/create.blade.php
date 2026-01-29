<x-admin.layouts.app>
    <x-page-title title="Add School" />

    <x-ui::card class="p-6">
        <form method="POST" action="{{ route('admin.schools.store') }}" class="space-y-6">
            @csrf
            @include('admin.schools._form', ['school' => null])

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.schools.index') }}">
                    <x-ui::button variant="secondary">Cancel</x-ui::button>
                </a>
                <x-ui::button type="reset" variant="secondary">Reset</x-ui::button>
                <x-ui::button type="submit">Create School</x-ui::button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>
