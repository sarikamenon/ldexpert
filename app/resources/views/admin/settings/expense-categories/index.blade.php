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

        <div class="overflow-x-auto">
            <table id="expenseCategoriesTable" class="w-full border-collapse display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
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
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-settings-expense-categories-index.js'])
    </x-slot>
</x-admin.layouts.app>
