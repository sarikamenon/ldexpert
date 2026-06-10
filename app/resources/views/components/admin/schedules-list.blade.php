@props([
    'filters' => [],
    'statuses' => [],
    'billingStatuses' => [],
    'ssas' => [],
    'therapists' => [],
    'context' => 'detail',
    /** When set, table uses server-side DataTables; empty tbody, load via AJAX. */
    'datatableUrl' => null,
    /** Student ID for filter_student_id in data request (when datatableUrl is set). */
    'studentId' => null,
    /** Default From/To bounds (also the clear-all reset target). */
    'defaultDateFrom' => null,
    'defaultDateTo' => null,
])

<x-ui::card class="p-6 space-y-6">
    <div class="space-y-2">
    <div class="flex flex-wrap items-center gap-3 justify-between">
        <form method="GET" id="scheduleFiltersForm" class="flex flex-wrap items-center gap-3">
            @if ($context === 'detail')
                <input type="hidden" name="tab" value="schedule">
            @endif

            <div class="flex items-center gap-2 shrink-0">
                <span class="text-sm font-medium text-foreground/70">From</span>
                <x-ui::input type="date" name="date_from" class="h-10" value="{{ $filters['date_from'] ?? $defaultDateFrom }}"
                    data-default-value="{{ $defaultDateFrom }}" aria-label="Schedule date from" />
                <span class="text-sm font-medium text-foreground/70">To</span>
                <x-ui::input type="date" name="date_to" class="h-10" value="{{ $filters['date_to'] ?? $defaultDateTo }}"
                    data-default-value="{{ $defaultDateTo }}" aria-label="Schedule date to" />
            </div>

            <x-ui::select name="status" :searchable="false" placeholder="Status: All" :inline="true" class="min-w-[12rem]">
                <option value="">Status: All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-ui::select>

            <x-ui::select name="billing_status" :searchable="false" placeholder="Billing: All" :inline="true" class="min-w-[12rem]">
                <option value="">Billing: All</option>
                @foreach ($billingStatuses as $billingStatus)
                    <option value="{{ $billingStatus->value }}" @selected(($filters['billing_status'] ?? null) === $billingStatus->value)>
                        {{ $billingStatus->label() }}
                    </option>
                @endforeach
            </x-ui::select>

            <x-ui::select name="ssa_id" searchable placeholder="SSA: All" :inline="true" class="min-w-[13rem]">
                <option value="">SSA: All</option>
                @foreach ($ssas as $ssa)
                    <option value="{{ $ssa->id }}" @selected(($filters['ssa_id'] ?? null) == $ssa->id)>
                        #{{ $ssa->id }} — {{ $ssa->primaryService?->name ?? 'Service' }}
                    </option>
                @endforeach
            </x-ui::select>

            <x-ui::select name="therapist_id" searchable placeholder="Therapist: All" :inline="true" class="min-w-[13rem]">
                <option value="">Therapist: All</option>
                @foreach ($therapists as $therapist)
                    <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                        {{ $therapist->name }} ({{ $therapist->email }})
                    </option>
                @endforeach
            </x-ui::select>

            <x-ui::button type="submit" size="lg">Filter</x-ui::button>
        </form>
    </div>

    <div
        id="scheduleFiltersSummary"
        class="hidden items-center gap-2 text-sm text-foreground-muted"
    >
        <span data-filter-count>0 filters applied</span>
        <span class="text-border">|</span>
        <button
            type="button"
            class="text-primary hover:underline font-medium"
            id="scheduleFiltersClearAll"
        >
            Clear all
        </button>
    </div>
    </div>

    <div class="overflow-x-auto">
        <table id="schedulesTable" class="min-w-full divide-y divide-border text-sm {{ isset($datatableUrl) ? 'display' : '' }}"
            @if(isset($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" data-student-id="{{ $studentId }}" data-filter-form="scheduleFiltersForm" @endif>
            <thead class="bg-muted/40">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Date</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Time</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Therapist</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">SSA</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Service</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">School/Family</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Status</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Billing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border"></tbody>
        </table>
    </div>
</x-ui::card>
