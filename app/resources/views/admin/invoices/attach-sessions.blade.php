<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-attach-sessions.js'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Add or remove sessions</h1>
                <p class="text-sm text-foreground/60 mt-1">Invoice {{ $invoice->invoice_number }}</p>
            </div>
            <a href="{{ route('admin.invoices.show', $invoice) }}"
                class="inline-flex items-center px-4 py-2 border border-border text-foreground rounded-lg text-sm font-medium hover:bg-background/subtle focus:outline-none focus:ring-2 focus:ring-ring">
                Back to invoice
            </a>
        </div>
    </div>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui::alert>
    @endif

    {{-- Filter form --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.invoices.attach-sessions', $invoice) }}" id="filterForm" class="space-y-4">
            <h2 class="text-lg font-semibold text-foreground">Filter sessions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="space-y-1">
                    <x-input-label for="filter_therapist_id" value="Therapist" />
                    <p class="mt-1 text-xs text-foreground/60" id="filter_therapist_id_help">Filter by therapist for this school.</p>
                    <x-ui::select id="filter_therapist_id" name="therapist_id" class="mt-1 block w-full" searchable placeholder="All Therapists" aria-describedby="filter_therapist_id_help">
                        <option value="">All Therapists</option>
                        @foreach ($therapists ?? [] as $t)
                            <option value="{{ $t->id }}" @selected(($filters['therapist_id'] ?? null) == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div class="space-y-1">
                    <x-input-label for="filter_student_id" value="Student" />
                    <p class="mt-1 text-xs text-foreground/60" id="filter_student_id_help">Filter by student for this school.</p>
                    <x-ui::select id="filter_student_id" name="student_id" class="mt-1 block w-full" searchable placeholder="All Students" aria-describedby="filter_student_id_help">
                        <option value="">All Students</option>
                        @foreach ($students ?? [] as $s)
                            <option value="{{ $s->id }}" @selected(($filters['student_id'] ?? null) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div class="space-y-1">
                    <x-input-label for="filter_service_id" value="Service" />
                    <p class="mt-1 text-xs text-foreground/60" id="filter_service_id_help">Filter by service type.</p>
                    <x-ui::select id="filter_service_id" name="service_id" class="mt-1 block w-full" searchable placeholder="All Services" aria-describedby="filter_service_id_help">
                        <option value="">All Services</option>
                        @foreach ($services ?? [] as $svc)
                            <option value="{{ $svc->id }}" @selected(($filters['service_id'] ?? null) == $svc->id)>{{ $svc->name }}</option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div class="space-y-1">
                    <x-input-label for="filter_date_from" value="Date from" />
                    <p class="mt-1 text-xs text-foreground/60" id="filter_date_from_help">Start of session date range.</p>
                    <x-ui::input type="date" id="filter_date_from" name="date_from" class="mt-1 block w-full"
                        value="{{ $filters['date_from'] ?? $invoice->billing_period_start->format('Y-m-d') }}" aria-describedby="filter_date_from_help" />
                </div>
                <div class="space-y-1">
                    <x-input-label for="filter_date_to" value="Date to" />
                    <p class="mt-1 text-xs text-foreground/60" id="filter_date_to_help">End of session date range.</p>
                    <x-ui::input type="date" id="filter_date_to" name="date_to" class="mt-1 block w-full"
                        value="{{ $filters['date_to'] ?? $invoice->billing_period_end->format('Y-m-d') }}" aria-describedby="filter_date_to_help" />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-ui::button type="submit">Apply filters</x-ui::button>
                <a href="{{ route('admin.invoices.attach-sessions', $invoice) }}">
                    <x-ui::button variant="secondary">Reset</x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    <form method="POST" action="{{ route('admin.invoices.attach-sessions.store', $invoice) }}" id="attachSessionsForm">
        @csrf

        <x-ui::card class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-foreground">Sessions</h2>
                <div class="flex items-center gap-2">
                    <x-ui::button type="button" id="selectAllBtn" variant="secondary">Select all</x-ui::button>
                    <x-ui::button type="button" id="deselectAllBtn" variant="secondary">Deselect all</x-ui::button>
                </div>
            </div>

            @if ($sessionLogs->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-border">
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70 w-10">
                                    <x-ui::checkbox id="selectAllCheckbox" aria-label="Select all sessions" />
                                </th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">School</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessionLogs as $log)
                                @php $isAttached = in_array($log->id, $attachedIds ?? [], true); @endphp
                                <tr class="border-b border-border hover:bg-background/subtle session-log-row">
                                    <td class="py-3 px-4">
                                        <x-ui::checkbox name="session_log_ids[]" value="{{ $log->id }}"
                                            class="session-log-checkbox"
                                            :checked="$isAttached"
                                            data-amount="{{ $log->school_invoice_amount ?? 0 }}"
                                            aria-label="Select session {{ $log->id }}" />
                                    </td>
                                    <td class="py-3 px-4 text-sm">{{ $log->session_date->format('M d, Y') }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->student->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->service->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->school->display_name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $log->duration_minutes }} min</td>
                                    <td class="py-3 px-4 text-sm font-medium">${{ number_format($log->school_invoice_amount ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui::empty-state
                    title="No sessions found"
                    description="No session logs match the current filters, or there are no uninvoiced sessions for this school in the selected period."
                />
            @endif

            <div class="mt-6 flex flex-col items-end gap-4">
                <div id="sessionLogsSummary" class="p-4 bg-background/subtle rounded-lg hidden">
                    <p class="text-sm font-medium">
                        <span id="selectedCount">0</span> session(s) selected |
                        Total: $<span id="selectedTotal">0.00</span>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.invoices.show', $invoice) }}">
                        <x-ui::button variant="secondary">Back to invoice</x-ui::button>
                    </a>
                    <x-ui::button type="submit" id="updateSessionsBtn">
                        Update sessions
                    </x-ui::button>
                </div>
            </div>
        </x-ui::card>
    </form>
</x-admin.layouts.app>
