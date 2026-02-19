<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

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

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="expenseCategoriesFiltersForm"
            :formAction="route('admin.settings.expense-categories.index')">
            <x-slot:filters>
                <x-ui::input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Category name" class="w-48" />

                <x-ui::select name="status" placeholder="All" :inline="true" :searchable="false" class="w-32">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </x-ui::select>

                @if (request()->hasAny(['search', 'status']) && array_filter(request()->only(['search', 'status'])))
                    <a href="{{ route('admin.settings.expense-categories.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>
        </x-ui::filter-toolbar>

        @if ($categories->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse expense-categories-table">
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
                                <td class="py-3 px-4 text-sm expense-category-status-cell">
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

                                        <button type="button"
                                            class="toggle-expense-category-status inline-flex items-center justify-center w-8 h-8 rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none {{ $category->is_active ? 'bg-warning text-warning-foreground hover:bg-warning/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                            data-category-id="{{ $category->id }}"
                                            data-status="{{ $category->is_active ? 'active' : 'inactive' }}"
                                            data-toggle-url="{{ route('admin.settings.expense-categories.toggle-status', $category) }}"
                                            title="{{ $category->is_active ? 'Deactivate Category' : 'Activate Category' }}"
                                            aria-label="{{ $category->is_active ? 'Deactivate category ' . $category->name : 'Activate category ' . $category->name }}">
                                            @if ($category->is_active)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                </svg>
                                            @endif
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $categories->withQueryString()->links() }}
            </div>
        @else
            <x-ui::empty-state title="No expense categories found"
                description="No categories match your current filters. Try adjusting your search criteria or create a new category." />
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-settings-expense-categories-index.js'])
    </x-slot>
</x-admin.layouts.app>
