@props([
    'schedules',
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
])

<x-ui::card class="p-6 space-y-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end" id="scheduleFiltersForm">
        @if ($context === 'detail')
            <input type="hidden" name="tab" value="schedule">
        @endif

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Date</label>
            <x-ui::input type="date" name="date" value="{{ $filters['date'] ?? '' }}" />
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Status</label>
            <x-ui::select name="status" :searchable="false" placeholder="All" :inline="true" width="180px">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Billing Status</label>
            <x-ui::select name="billing_status" :searchable="false" placeholder="All" :inline="true" width="180px">
                <option value="">All</option>
                @foreach ($billingStatuses as $billingStatus)
                    <option value="{{ $billingStatus->value }}" @selected(($filters['billing_status'] ?? null) === $billingStatus->value)>
                        {{ $billingStatus->label() }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">SSA</label>
            <x-ui::select name="ssa_id" searchable placeholder="All" :inline="true" width="220px">
                <option value="">All</option>
                @foreach ($ssas as $ssa)
                    <option value="{{ $ssa->id }}" @selected(($filters['ssa_id'] ?? null) == $ssa->id)>
                        #{{ $ssa->id }} — {{ $ssa->primaryService?->name ?? 'Service' }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Therapist</label>
            <x-ui::select name="therapist_id" searchable placeholder="All" :inline="true" width="220px">
                <option value="">All</option>
                @foreach ($therapists as $therapist)
                    <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                        {{ $therapist->name }} ({{ $therapist->email }})
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        <div class="flex gap-2">
            <x-ui::button type="submit">Apply</x-ui::button>
            <a href="{{ request()->url() }}?tab=schedule">
                <x-ui::button variant="secondary">Reset</x-ui::button>
            </a>
        </div>
    </form>

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
            <tbody class="divide-y divide-border">
                @if(isset($datatableUrl))
                @else
                @forelse ($schedules as $schedule)
                    @php
                        $rowTz = $schedule->displayTimezone();
                        $rowLocalStart = $schedule->localStart($rowTz);
                        $rowLocalEnd = $schedule->localEnd($rowTz);
                    @endphp
                    <tr>
                        <td class="px-3 py-2">{{ $rowLocalStart->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">
                            {{ $rowLocalStart->format(config('display.time')) }} - {{ $rowLocalEnd->format(config('display.time')) }}
                        </td>
                        <td class="px-3 py-2">
                            @if ($schedule->therapist)
                                <a href="{{ route('admin.therapists.show', $schedule->therapist) }}"
                                    class="text-primary hover:underline">
                                    {{ $schedule->therapist->name }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($schedule->ssa)
                                <a href="{{ route('admin.ssas.show', $schedule->ssa) }}"
                                    class="text-primary hover:underline">
                                    #{{ $schedule->ssa->id }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $schedule->service?->name ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $schedule->school?->display_name ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <x-ui::badge :variant="$schedule->status?->value === 'completed' ? 'success' : ($schedule->status?->value === 'cancelled' ? 'danger' : 'secondary')">
                                {{ $schedule->status?->label() ?? '—' }}
                            </x-ui::badge>
                        </td>
                        <td class="px-3 py-2">
                            <x-ui::badge :variant="$schedule->billing_status?->value === 'billed' ? 'success' : 'secondary'">
                                {{ $schedule->billing_status?->label() ?? '—' }}
                            </x-ui::badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-6 text-center text-foreground/70" colspan="8">No schedules found</td>
                    </tr>
                @endforelse
                @endif
            </tbody>
        </table>
    </div>

    @if(!isset($datatableUrl) && method_exists($schedules, 'links'))
    <div>
        {{ $schedules->withQueryString()->links() }}
    </div>
    @endif
</x-ui::card>
