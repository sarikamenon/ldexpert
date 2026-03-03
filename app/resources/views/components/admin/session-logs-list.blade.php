@props([
    'sessionLogs' => null,
    'columns' => [],
    'rows' => [],
    'filters' => [],
    'statuses' => [],
    // context: 'index', 'detail', or 'therapist'
    'context' => 'index',
    'datatableUrl' => null,
    'therapistId' => null,
    'studentId' => null,
    'ssaId' => null,
])

<x-ui::card class="p-6 space-y-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end w-full" id="sessionLogsFiltersForm">
        @if ($context === 'detail' && empty($datatableUrl))
            <input type="hidden" name="tab" value="session_logs">
        @endif
        @if ($therapistId)
            <input type="hidden" name="therapist_id" value="{{ $therapistId }}">
        @endif
        @if ($studentId)
            <input type="hidden" name="student_id" value="{{ $studentId }}">
        @endif
        @if ($ssaId)
            <input type="hidden" name="ssa_id" value="{{ $ssaId }}">
        @endif

        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-foreground/70 mb-1">From Date</label>
            <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
        </div>

        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-foreground/70 mb-1">To Date</label>
            <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" />
        </div>

        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-foreground/70 mb-1">Status</label>
            <x-ui::select name="status" :searchable="false" placeholder="All Statuses">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        @if (empty($datatableUrl))
            <button type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Apply Filters
            </button>

            @if (!empty(array_filter($filters)))
                <a href="{{ $context === 'detail' ? request()->url() . '?tab=session_logs' : route(Route::currentRouteName()) }}"
                    class="px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Clear
                </a>
            @endif
        @endif
    </form>

    <div id="sessionLogsStatus" class="sr-only" role="status" aria-live="polite"></div>

    @if (!empty($datatableUrl))
        <div class="overflow-x-auto">
            <table id="sessionLogsTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Entry Info</th>
                        <th>Student / School</th>
                        <th>Therapist / Service</th>
                        <th>School Amount</th>
                        <th>Therapist Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    @elseif ($sessionLogs && $sessionLogs->total() > 0)
        <div class="overflow-x-auto">
            <x-ui::session-log-table :columns="$columns" :rows="$rows" />
        </div>

        <div class="mt-4">
            {{ $sessionLogs->links() }}
        </div>
    @else
        <x-ui::empty-state title="No session logs found."
            description="Try adjusting your filters or expanding your date range to see more results.">
            <x-slot:icon>
                <svg class="mx-auto h-12 w-12 text-foreground/40" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            </x-slot:icon>
        </x-ui::empty-state>
    @endif
</x-ui::card>
