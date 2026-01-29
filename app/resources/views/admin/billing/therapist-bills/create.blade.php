<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapist-bills-create.js'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Create Therapist Bill</h1>
                <p class="text-sm text-foreground/60 mt-1">Select approved session logs to include in the bill</p>
            </div>
            <a href="{{ route('admin.billing.therapist-bills.index') }}"
                class="inline-flex items-center px-4 py-2 border border-border text-foreground rounded-lg text-sm font-medium hover:bg-background/subtle">
                Back to Bills
            </a>
        </div>
    </div>

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui::alert>
    @endif

    {{-- Filter Form --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.billing.therapist-bills.create') }}" id="filterForm"
            class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="space-y-1">
                    <label for="filter_therapist_id" class="text-sm font-medium text-foreground/70">Therapist</label>
                    <select id="filter_therapist_id" name="therapist_id"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">All Therapists</option>
                        @foreach ($therapists ?? [] as $therapist)
                            <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="filter_student_id" class="text-sm font-medium text-foreground/70">Student</label>
                    <select id="filter_student_id" name="student_id"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">All Students</option>
                        @foreach ($students ?? [] as $student)
                            <option value="{{ $student->id }}" @selected(($filters['student_id'] ?? null) == $student->id)>
                                {{ $student->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="filter_service_id" class="text-sm font-medium text-foreground/70">Service</label>
                    <select id="filter_service_id" name="service_id"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">All Services</option>
                        @foreach ($services ?? [] as $service)
                            <option value="{{ $service->id }}" @selected(($filters['service_id'] ?? null) == $service->id)>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="filter_school_id" class="text-sm font-medium text-foreground/70">School</label>
                    <select id="filter_school_id" name="school_id"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">All Schools</option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label for="filter_date_from" class="text-sm font-medium text-foreground/70">Date From</label>
                    <x-ui::input type="date" id="filter_date_from" name="date_from"
                        value="{{ $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d') }}" />
                </div>

                <div class="space-y-1">
                    <label for="filter_date_to" class="text-sm font-medium text-foreground/70">Date To</label>
                    <x-ui::input type="date" id="filter_date_to" name="date_to"
                        value="{{ $filters['date_to'] ?? now()->format('Y-m-d') }}" />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <x-ui::button type="submit">
                    Apply Filters
                </x-ui::button>
                <a href="{{ route('admin.billing.therapist-bills.create') }}">
                    <x-ui::button variant="secondary">
                        Reset
                    </x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    <form method="POST" action="{{ route('admin.billing.therapist-bills.store') }}" id="createBillForm">
        @csrf

        {{-- Bill Details --}}
        <x-ui::card class="p-6 space-y-6 mb-6">
            <h2 class="text-lg font-semibold text-foreground">Bill Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label for="therapist_id" class="text-sm font-medium text-foreground">Therapist <span
                            class="text-red-500">*</span></label>
                    <p class="mt-1 text-xs text-foreground/60">Therapist to bill for the selected session logs</p>
                    <select id="therapist_id" name="therapist_id" required
                        class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Therapist</option>
                        @foreach ($therapists ?? [] as $therapist)
                            <option value="{{ $therapist->id }}" @selected(old('therapist_id', $filters['therapist_id'] ?? null) == $therapist->id)>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('therapist_id')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label for="bill_date" class="text-sm font-medium text-foreground">Bill Date <span
                            class="text-red-500">*</span></label>
                    <p class="mt-1 text-xs text-foreground/60">Date when the bill is issued</p>
                    <x-ui::input type="date" id="bill_date" name="bill_date" required
                        value="{{ old('bill_date', now()->format('Y-m-d')) }}" class="mt-1" />
                    @error('bill_date')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label for="bill_number" class="text-sm font-medium text-foreground">Bill Number</label>
                    <p class="mt-1 text-xs text-foreground/60">Auto-generated number is shown. You can edit if needed.
                    </p>
                    <x-ui::input type="text" id="bill_number" name="bill_number"
                        value="{{ old('bill_number', $billNumber ?? '') }}" class="mt-1" />
                    @error('bill_number')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label for="billing_period_start" class="text-sm font-medium text-foreground">Billing Period Start
                        <span class="text-red-500">*</span></label>
                    <p class="mt-1 text-xs text-foreground/60">Start date of the billing period covered by this bill
                    </p>
                    <x-ui::input type="date" id="billing_period_start" name="billing_period_start" required
                        value="{{ old('billing_period_start', $filters['date_from'] ?? now()->subDays(30)->format('Y-m-d')) }}" class="mt-1" />
                    @error('billing_period_start')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label for="billing_period_end" class="text-sm font-medium text-foreground">Billing Period End
                        <span class="text-red-500">*</span></label>
                    <p class="mt-1 text-xs text-foreground/60">End date of the billing period covered by this bill</p>
                    <x-ui::input type="date" id="billing_period_end" name="billing_period_end" required
                        value="{{ old('billing_period_end', $filters['date_to'] ?? now()->format('Y-m-d')) }}" class="mt-1" />
                    @error('billing_period_end')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label for="due_date" class="text-sm font-medium text-foreground">Due Date</label>
                    <p class="mt-1 text-xs text-foreground/60">Date when payment is due (default: 30 days from bill
                        date)</p>
                    <x-ui::input type="date" id="due_date" name="due_date"
                        value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}" class="mt-1" />
                    @error('due_date')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-1 md:col-span-2">
                    <label for="notes" class="text-sm font-medium text-foreground">Notes (Optional)</label>
                    <p class="mt-1 text-xs text-foreground/60">Additional notes or comments for this bill</p>
                    <textarea id="notes" name="notes" rows="3"
                        class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-sm text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </x-ui::card>

        {{-- Session Logs Selection --}}
        <x-ui::card class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-foreground">Select Session Logs</h2>
                <div class="flex items-center gap-4">
                    <button type="button" id="selectAllBtn" class="text-sm text-primary hover:underline">Select
                        All</button>
                    <button type="button" id="deselectAllBtn" class="text-sm text-primary hover:underline">Deselect
                        All</button>
                </div>
            </div>

            <div id="sessionLogsSummary" class="mb-4 p-4 bg-background/subtle rounded-lg hidden">
                <p class="text-sm font-medium">
                    <span id="selectedCount">0</span> session(s) selected |
                    Total: $<span id="selectedTotal">0.00</span>
                </p>
            </div>

            @if ($sessionLogs->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">
                                    <x-ui::checkbox id="selectAllCheckbox" />
                                </th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">School</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                                <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessionLogs as $log)
                                <tr class="border-b border-border hover:bg-background/subtle session-log-row"
                                    data-therapist-id="{{ $log->therapist_id }}">
                                    <td class="py-3 px-4">
                                        <x-ui::checkbox name="session_log_ids[]" value="{{ $log->id }}"
                                            class="session-log-checkbox"
                                            data-amount="{{ $log->therapist_billable_amount ?? 0 }}"
                                            data-therapist-id="{{ $log->therapist_id }}" />
                                    </td>
                                    <td class="py-3 px-4 text-sm">{{ $log->session_date->format('M d, Y') }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->student->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->service->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->school->display_name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->duration_minutes }} min</td>
                                    <td class="py-3 px-4 text-sm font-medium">
                                        ${{ number_format($log->therapist_billable_amount ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12 text-foreground/60">
                    <p>No approved session logs available for billing.</p>
                    <p class="text-sm mt-2">Try adjusting your filters to see more results.</p>
                </div>
            @endif
        </x-ui::card>

        <div class="mt-6 flex items-center justify-end gap-4">
            <a href="{{ route('admin.billing.therapist-bills.index') }}">
                <x-ui::button variant="secondary">
                    Cancel
                </x-ui::button>
            </a>
            <x-ui::button type="submit">
                Create Bill
            </x-ui::button>
        </div>
    </form>
</x-admin.layouts.app>
