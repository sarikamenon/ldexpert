<x-admin.layouts.app>
    <x-page-title title="Edit School" />

    <x-ui::card class="p-6">
        <form method="POST" action="{{ route('admin.schools.update', $school) }}" class="space-y-6">
            @csrf
            @method('PATCH')
            @include('admin.schools._form', ['school' => $school])

            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.schools.index') }}">
                    <x-ui::button variant="secondary">Cancel</x-ui::button>
                </a>
                <x-ui::button type="submit">Update School</x-ui::button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>
