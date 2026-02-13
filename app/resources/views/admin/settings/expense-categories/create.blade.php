<x-admin.layouts.app>
    <x-ui::show-header title="Create Expense Category" subtitle="Add a new category for organizing expenses"
        :back-url="route('admin.settings.expense-categories.index')" back-label="Back to Categories" />

    <x-ui::card class="p-6">
        <form method="POST" action="{{ route('admin.settings.expense-categories.store') }}">
            @csrf

            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="name">
                        Category Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary @error('name') border-danger focus:ring-danger @enderror">
                    @error('name')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-foreground/60">
                        This will be used to categorize expenses. The slug will be generated automatically.
                    </p>
                </div>

                <div>
                    <div class="flex items-center gap-3">
                        <input id="is_active" name="is_active" type="checkbox" value="1"
                            class="rounded border-border text-primary focus:ring-primary"
                            {{ old('is_active', true) ? 'checked' : '' }}>
                        <div>
                            <label for="is_active" class="text-sm font-medium text-foreground">Active</label>
                            <p class="mt-0.5 text-xs text-foreground/60">
                                Inactive categories won't be available when creating expenses.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-6 border-t border-border">
                <a href="{{ route('admin.settings.expense-categories.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Cancel
                </a>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Create Category
                </button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>
