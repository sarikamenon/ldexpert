<x-admin.layouts.app>
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

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.expenses.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">Category</label>
                    <select name="category_id"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}"
                                {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Vendor, description, reference..."
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Apply Filters
                </button>
                <a href="{{ route('admin.expenses.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Clear Filters
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Summary --}}
    @if ($expenses->count() > 0)
        <x-ui::card class="p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/70">Total Expenses</p>
                    <p class="text-2xl font-bold mt-1">${{ number_format($totalAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-foreground/70">Number of Expenses</p>
                    <p class="text-2xl font-bold mt-1">{{ $expenses->total() }}</p>
                </div>
            </div>
        </x-ui::card>
    @endif

    {{-- Expenses List --}}
    <x-ui::card class="overflow-hidden">
        @if ($expenses->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
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
                                                class="inline" x-data="{ confirmDelete: false }"
                                                x-on:submit.prevent="if (confirmDelete || confirm('Are you sure you want to delete this expense?')) $el.submit()">
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

            <div class="px-6 py-4 border-t border-border">
                {{ $expenses->links() }}
            </div>
        @else
            <x-ui::empty-state title="No expenses found"
                description="No expenses match your current filters. Try adjusting your search criteria or add a new expense." />
        @endif
    </x-ui::card>
</x-admin.layouts.app>
