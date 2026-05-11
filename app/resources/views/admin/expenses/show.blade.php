<x-admin.layouts.app>
    <x-ui::show-header :title="'Expense #' . $expense->id"
        :subtitle="$expense->expense_date->format('M d, Y') . ' • ' . $expense->category->name"
        :back-url="route('admin.expenses.index')" back-label="Back to Expenses">
        <x-slot name="actions">
            @can('update', $expense)
                <a href="{{ route('admin.expenses.edit', $expense) }}">
                    <x-ui::button>
                        Edit
                    </x-ui::button>
                </a>
            @endcan
            @can('delete', $expense)
                <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" class="inline"
                    x-data="{ confirmDelete: false }"
                    x-on:submit.prevent="if (confirmDelete || confirm('Are you sure you want to delete this expense?')) $el.submit()">
                    @csrf
                    @method('DELETE')
                    <x-ui::button type="submit" variant="danger">
                        Delete
                    </x-ui::button>
                </form>
            @endcan
        </x-slot>
    </x-ui::show-header>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    @if ($expense->source_type)
        <x-ui::alert variant="info" class="mb-4">
            This expense was auto-created from another record and is managed there.
            It cannot be edited or deleted directly.
        </x-ui::alert>
    @endif

    <x-ui::card class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Expense Details</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-foreground/70">Date</p>
                        <p class="text-sm font-medium mt-1">{{ $expense->expense_date->format('M d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-foreground/70">Category</p>
                        <p class="text-sm font-medium mt-1">
                            <span
                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                {{ $expense->category->name }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-foreground/70">Amount</p>
                        <p class="text-2xl font-bold mt-1">${{ number_format($expense->amount, 2) }}</p>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Additional Information</h3>
                <div class="space-y-3">
                    @if ($expense->vendor_payee)
                        <div>
                            <p class="text-sm text-foreground/70">Vendor/Payee</p>
                            <p class="text-sm font-medium mt-1">{{ $expense->vendor_payee }}</p>
                        </div>
                    @endif
                    @if ($expense->reference)
                        <div>
                            <p class="text-sm text-foreground/70">Reference</p>
                            <p class="text-sm font-medium mt-1">{{ $expense->reference }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-sm text-foreground/70">Recorded By</p>
                        <p class="text-sm font-medium mt-1">{{ $expense->createdBy?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-foreground/70">Created At</p>
                        <p class="text-sm font-medium mt-1">{{ $expense->created_at->format(config('display.datetime')) }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($expense->description)
            <div class="mt-6 pt-6 border-t border-border">
                <h3 class="text-sm font-medium text-foreground/70 mb-2">Description</h3>
                <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $expense->description }}</p>
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>
