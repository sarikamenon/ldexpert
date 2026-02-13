<x-admin.layouts.app>
    <x-ui::page-header title="Expense Categories" subtitle="Manage categories used to classify expenses">
        <x-slot name="actions">
            <a href="{{ route('admin.settings.expense-categories.create') }}">
                <x-ui::button variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Category
                </x-ui::button>
            </a>
        </x-slot>
    </x-ui::page-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.settings.expense-categories.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="search">
                        Search
                    </label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}"
                        placeholder="Category name"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1" for="status">
                        Status
                    </label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Apply Filters
                </button>
                <a href="{{ route('admin.settings.expense-categories.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Clear Filters
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Categories Table --}}
    <x-ui::card class="overflow-hidden">
        @if ($categories->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead class="bg-background/subtle">
                        <tr>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Name</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Slug</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Expenses</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Created</th>
                            <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm font-medium">
                                    {{ $category->name }}
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <code class="text-xs bg-background/subtle px-2 py-1 rounded">
                                        {{ $category->slug }}
                                    </code>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    @if ($category->is_active)
                                        <x-ui::badge variant="success">Active</x-ui::badge>
                                    @else
                                        <x-ui::badge variant="secondary">Inactive</x-ui::badge>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ $category->expenses_count }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground/70">
                                    {{ $category->created_at->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.settings.expense-categories.edit', $category) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="Edit Category"
                                            aria-label="Edit category {{ $category->name }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                </path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                </path>
                                            </svg>
                                        </a>

                                        @if ($category->expenses_count === 0)
                                            <form method="POST"
                                                action="{{ route('admin.settings.expense-categories.destroy', $category) }}"
                                                onsubmit="return confirm('Are you sure you want to delete this category?');"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    title="Delete Category"
                                                    aria-label="Delete category {{ $category->name }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                                        <path d="M10 11v6"></path>
                                                        <path d="M14 11v6"></path>
                                                        <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-foreground/60">
                                                In use (cannot delete)
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-border">
                {{ $categories->links() }}
            </div>
        @else
            <x-ui::empty-state title="No expense categories found"
                description="No categories match your current filters. Try adjusting your search criteria or create a new category." />
        @endif
    </x-ui::card>
</x-admin.layouts.app>
