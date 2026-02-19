<x-admin.layouts.app>
    <x-ui::show-header title="Add Expense" subtitle="Record a new business expense" :back-url="route('admin.expenses.index')"
        back-label="Back to Expenses" />

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6">
        <form method="POST" action="{{ route('admin.expenses.store') }}">
            @csrf

            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            Expense Date *
                        </label>
                        <input type="date" name="expense_date" value="{{ old('expense_date', date('Y-m-d')) }}"
                            max="{{ date('Y-m-d') }}" required
                            class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        @error('expense_date')
                            <p class="text-sm text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            Category *
                        </label>
                        <select name="expense_category_id" required
                            class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                            <option value="">Select category...</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                    {{ old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('expense_category_id')
                            <p class="text-sm text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            Amount *
                        </label>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}"
                            required
                            class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        @error('amount')
                            <p class="text-sm text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1">
                            Vendor/Payee
                        </label>
                        <input type="text" name="vendor_payee" value="{{ old('vendor_payee') }}"
                            placeholder="Who was this expense paid to?"
                            class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                        @error('vendor_payee')
                            <p class="text-sm text-danger mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">
                        Reference Number
                    </label>
                    <input type="text" name="reference" value="{{ old('reference') }}"
                        placeholder="Receipt number, invoice number, etc."
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">
                    @error('reference')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground mb-1">
                        Description
                    </label>
                    <textarea name="description" rows="4" placeholder="What was this expense for?"
                        class="w-full px-3 py-2 border border-border rounded-md focus:ring-2 focus:ring-primary">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-6 border-t border-border">
                <a href="{{ route('admin.expenses.index') }}"
                    class="px-4 py-2 border border-border rounded-md hover:bg-background/subtle">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90">
                    Add Expense
                </button>
            </div>
        </form>
    </x-ui::card>
</x-admin.layouts.app>
