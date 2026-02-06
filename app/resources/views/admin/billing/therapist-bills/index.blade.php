<x-admin.layouts.app>
    <x-page-title title="Therapist Bills" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="therapistBillsFiltersForm">
            <x-slot:filters>
                <x-ui::select name="therapist_id" searchable placeholder="All Therapists" :inline="true" class="w-40">
                    <option value="">All Therapists</option>
                    @foreach ($therapists ?? [] as $therapist)
                        <option value="{{ $therapist->id }}" @selected((int) ($filters['therapist_id'] ?? 0) === $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true" class="w-36">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\TherapistBillStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                <x-ui::input type="text" name="bill_number" value="{{ $filters['bill_number'] ?? '' }}"
                    placeholder="Bill #" class="w-32" />

                @if (!empty(array_filter($filters ?? [])))
                    <a href="{{ route('admin.billing.therapist-bills.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.billing.therapist-bills.create') }}">
                    <x-ui::button>Create Bill</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        @if ($bills->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Bill #</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Therapist</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Period</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Total</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Due Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bills as $bill)
                            <tr class="border-b border-border hover:bg-background/subtle">
                                <td class="py-3 px-4">
                                    <a href="{{ route('admin.billing.therapist-bills.show', $bill) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $bill->bill_number }}
                                    </a>
                                </td>
                                <td class="py-3 px-4">{{ $bill->therapist_name ?? '—' }}</td>
                                <td class="py-3 px-4 text-sm text-foreground/70">
                                    {{ $bill->billing_period_start->format('M d') }} -
                                    {{ $bill->billing_period_end->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4 font-medium">${{ number_format($bill->total_due, 2) }}</td>
                                <td class="py-3 px-4">
                                    <x-ui::badge :variant="match ($bill->status) {
                                        \App\Enums\TherapistBillStatus::DRAFT => 'secondary',
                                        \App\Enums\TherapistBillStatus::SENT => 'primary',
                                        \App\Enums\TherapistBillStatus::PAID => 'success',
                                        default => 'secondary',
                                    }">
                                        {{ $bill->status?->label() }}
                                    </x-ui::badge>
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground/70">
                                    {{ $bill->due_date->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.billing.therapist-bills.show', $bill) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            title="View Bill"
                                            aria-label="View bill {{ $bill->bill_number }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.billing.therapist-bills.download', $bill) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            title="Download PDF"
                                            aria-label="Download bill {{ $bill->bill_number }} as PDF">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                </path>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $bills->links() }}
            </div>
        @else
            <x-ui::empty-state title="No therapist bills found."
                description="Adjust your filters or create a new bill to see it listed here." />
        @endif
    </x-ui::card>
</x-admin.layouts.app>
