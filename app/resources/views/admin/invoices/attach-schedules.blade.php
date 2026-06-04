<x-admin.layouts.app>
    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-invoices-attach-sessions.js'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Add or remove scheduled sessions</h1>
                <p class="text-sm text-foreground/60 mt-1">Advance invoice {{ $invoice->invoice_number }}</p>
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

    <form method="POST" action="{{ route('admin.invoices.attach-sessions.store', $invoice) }}" id="attachSessionsForm">
        @csrf

        <x-ui::card class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Scheduled sessions</h2>
                    <p class="mt-1 text-xs text-foreground/60">
                        Schedules in {{ $invoice->billing_period_start?->format('M d, Y') }} – {{ $invoice->billing_period_end?->format('M d, Y') }} for this school or family. Charged in advance.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui::button type="button" id="selectAllBtn" variant="secondary">Select all</x-ui::button>
                    <x-ui::button type="button" id="deselectAllBtn" variant="secondary">Deselect all</x-ui::button>
                </div>
            </div>

            @if ($scheduleRows->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="border-b border-border">
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70 w-10">
                                    <x-ui::checkbox id="selectAllCheckbox" aria-label="Select all schedules" />
                                </th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Student</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Service</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Therapist</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Duration</th>
                                <th scope="col" class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($scheduleRows as $row)
                                <tr class="border-b border-border hover:bg-background/subtle">
                                    <td class="py-3 px-4">
                                        <x-ui::checkbox name="schedule_ids[]" value="{{ $row['schedule']->id }}"
                                            class="session-log-checkbox"
                                            :checked="$row['attached']"
                                            data-amount="{{ $row['amount'] }}"
                                            aria-label="Select schedule {{ $row['schedule']->id }}" />
                                    </td>
                                    <td class="py-3 px-4 text-sm">{{ $row['schedule']->schedule_date->format('M d, Y') }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $row['schedule']->student->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $row['schedule']->service->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $row['schedule']->therapist->name ?? '—' }}</td>
                                    <td class="py-3 px-4 text-sm">{{ $row['schedule']->durationMinutes() }} min</td>
                                    <td class="py-3 px-4 text-sm font-medium">${{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ui::empty-state
                    title="No schedules found"
                    description="There are no uninvoiced scheduled sessions for this school or family in the selected period."
                />
            @endif

            <div class="mt-6 flex flex-col items-end gap-4">
                <div id="sessionLogsSummary" class="p-4 bg-background/subtle rounded-lg hidden">
                    <p class="text-sm font-medium">
                        <span id="selectedCount">0</span> schedule(s) selected |
                        Total: $<span id="selectedTotal">0.00</span>
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.invoices.show', $invoice) }}">
                        <x-ui::button variant="secondary">Back to invoice</x-ui::button>
                    </a>
                    <x-ui::button type="submit" id="updateSessionsBtn">
                        Update schedules
                    </x-ui::button>
                </div>
            </div>
        </x-ui::card>
    </form>
</x-admin.layouts.app>
