<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-ui::page-header title="Expenses" subtitle="Track and manage business expenses">
        <x-slot name="actions">
            <a href="{{ route('admin.expenses.create') }}">
                <x-ui::button variant="primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Expense
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
        <x-ui::filter-toolbar formId="expensesFiltersForm" :formAction="route('admin.expenses.index')">
            <x-slot:filters>
                <x-ui::select name="category_id" placeholder="All Categories" :inline="true" class="w-40">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                            {{ request('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ request('date_from') }}"
                    title="Date From" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ request('date_to') }}"
                    title="Date To" class="w-36" />

                <x-ui::input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Vendor, description, reference..." class="w-48" />

                @if (request()->hasAny(['category_id', 'date_from', 'date_to', 'search']) && array_filter(request()->only(['category_id', 'date_from', 'date_to', 'search'])))
                    <a href="{{ route('admin.expenses.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>
        </x-ui::filter-toolbar>

        <p class="text-sm text-foreground/70" id="expensesSummary">
            Total: $<span id="expensesTotalAmount">0.00</span> · <span id="expensesCount">0</span> expense(s)
        </p>

        <div class="overflow-x-auto">
            <table id="expensesTable" class="w-full border-collapse display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Category</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Vendor/Payee</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Description</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Amount</th>
                        <th class="text-right py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-expenses-index.js'])
    </x-slot>
</x-admin.layouts.app>
