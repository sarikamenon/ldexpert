<x-admin.layouts.app>
    <x-ui::show-header title="Edit Expense Category" subtitle="Update the details of this expense category"
        :back-url="route('admin.settings.expense-categories.index')" back-label="Back to Categories" />

    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] gap-6">
        <x-ui::card class="p-6">
            <form method="POST" action="{{ route('admin.settings.expense-categories.update', $expenseCategory) }}">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1" for="name">
                            Category Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="name" name="name"
                            value="{{ old('name', $expenseCategory->name) }}" required autofocus
                            class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary @error('name') border-danger focus:ring-danger @enderror">
                        @error('name')
                            <p class="text-sm text-danger mt-1">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-foreground/60">
                            Current slug:
                            <code>{{ $expenseCategory->slug }}</code>
                            (will be updated automatically).
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center gap-3">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                class="rounded border-border text-primary focus:ring-primary"
                                {{ old('is_active', $expenseCategory->is_active) ? 'checked' : '' }}>
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
                        Update Category
                    </button>
                </div>
            </form>
        </x-ui::card>

        <div class="space-y-3">
            <x-ui::card class="p-4">
                <h5 class="text-sm font-semibold mb-3">Category Info</h5>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="font-medium text-foreground/80">Expenses</dt>
                        <dd class="text-foreground">
                            {{ $expenseCategory->expenses()->count() }} expense(s) use this category
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-foreground/80">Created</dt>
                        <dd class="text-foreground">
                            {{ $expenseCategory->created_at->format('M d, Y') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-foreground/80">Last Updated</dt>
                        <dd class="text-foreground">
                            {{ $expenseCategory->updated_at->format('M d, Y') }}
                        </dd>
                    </div>
                </dl>
            </x-ui::card>

            @if ($expenseCategory->expenses()->count() > 0)
                <x-ui::card class="p-4 bg-warning/10 border-warning/40">
                    <h6 class="text-sm font-semibold text-warning mb-2">Note</h6>
                    <p class="text-xs text-foreground/80">
                        This category cannot be deleted because it has associated expenses.
                        You can deactivate it instead.
                    </p>
                </x-ui::card>
            @endif
        </div>
    </div>
</x-admin.layouts.app>
