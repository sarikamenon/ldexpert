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

        @if ($expenses->count() > 0)
            <p class="text-sm text-foreground/70">
                Total: ${{ number_format($totalAmount, 2) }} · {{ $expenses->total() }} expense(s)
            </p>
        @endif

        @if ($expenses->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse expenses-table">
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
                        @foreach ($expenses as $expense)
                            <tr class="border-t border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm">{{ $expense->expense_date->format('M d, Y') }}</td>
                                <td class="py-3 px-4 text-sm">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ $expense->category->name }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm">{{ $expense->vendor_payee ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm">
                                    {{ Str::limit($expense->description, 50) ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right font-medium">
                                    ${{ number_format($expense->amount, 2) }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.expenses.show', $expense) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="View Expense"
                                            aria-label="View expense #{{ $expense->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        @can('update', $expense)
                                            <a href="{{ route('admin.expenses.edit', $expense) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                title="Edit Expense"
                                                aria-label="Edit expense #{{ $expense->id }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                    </path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                    </path>
                                                </svg>
                                            </a>
                                        @endcan
                                        @can('delete', $expense)
                                            <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}"
                                                class="inline expense-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    title="Delete Expense"
                                                    aria-label="Delete expense #{{ $expense->id }}">
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
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $expenses->withQueryString()->links() }}
            </div>
        @else
            <x-ui::empty-state title="No expenses found"
                description="No expenses match your current filters. Try adjusting your search criteria or add a new expense." />
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-expenses-index.js'])
    </x-slot>
</x-admin.layouts.app>
