@props([
    'sessionLogs',
    'columns' => [],
    'rows' => [],
    'filters' => [],
    'statuses' => [],
    // context: 'index', 'detail', or 'therapist'
    'context' => 'index',
])

<x-ui::card class="p-6 space-y-6">
    <div class="flex flex-col md:flex-row md:flex-wrap gap-4 items-start md:items-center justify-between">
        <form method="GET" class="flex flex-wrap gap-3 w-full md:flex-1 md:max-w-3xl" id="sessionLogsFiltersForm">
            @if ($context === 'detail')
                <input type="hidden" name="tab" value="session_logs">
            @endif

            <input type="date" name="date_from"
                class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                value="{{ $filters['date_from'] ?? '' }}" placeholder="From Date">

            <input type="date" name="date_to"
                class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                value="{{ $filters['date_to'] ?? '' }}" placeholder="To Date">

            <select name="status"
                class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>

            <select name="per_page"
                class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="15" @selected(($filters['per_page'] ?? 15) == 15)>15 per page</option>
                <option value="30" @selected(($filters['per_page'] ?? 15) == 30)>30 per page</option>
                <option value="50" @selected(($filters['per_page'] ?? 15) == 50)>50 per page</option>
                <option value="100" @selected(($filters['per_page'] ?? 15) == 100)>100 per page</option>
            </select>

            <button type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Apply Filters
            </button>

            @if (!empty(array_filter($filters)))
                <a href="{{ $context === 'detail' ? request()->url() . '?tab=session_logs' : route(Route::currentRouteName()) }}"
                    class="px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                    Clear
                </a>
            @endif
        </form>
    </div>

    @if ($sessionLogs->total() > 0)
        <div class="overflow-x-auto">
            <x-ui::session-log-table :columns="$columns" :rows="$rows" />
        </div>

        <div class="mt-4">
            {{ $sessionLogs->links() }}
        </div>
    @else
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
            </svg>
            <p class="mt-2 text-sm text-foreground/70">No session logs found matching your criteria.</p>
        </div>
    @endif
</x-ui::card>
