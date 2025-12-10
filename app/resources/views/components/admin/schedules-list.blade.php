@props([
    'schedules',
    'filters' => [],
    'statuses' => [],
    'billingStatuses' => [],
    'ssas' => [],
    'therapists' => [],
    'context' => 'detail',
])

<x-ui::card class="p-6 space-y-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end" id="scheduleFiltersForm">
        @if ($context === 'detail')
            <input type="hidden" name="tab" value="schedule">
        @endif

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Date</label>
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary" />
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Status</label>
            <select name="status"
                class="border border-border rounded-lg px-3 py-2 text-sm min-w-[180px] focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="">All</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Billing Status</label>
            <select name="billing_status"
                class="border border-border rounded-lg px-3 py-2 text-sm min-w-[180px] focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="">All</option>
                @foreach ($billingStatuses as $billingStatus)
                    <option value="{{ $billingStatus->value }}" @selected(($filters['billing_status'] ?? null) === $billingStatus->value)>
                        {{ $billingStatus->label() }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">SSA</label>
            <select name="ssa_id"
                class="border border-border rounded-lg px-3 py-2 text-sm min-w-[220px] focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="">All</option>
                @foreach ($ssas as $ssa)
                    <option value="{{ $ssa->id }}" @selected(($filters['ssa_id'] ?? null) == $ssa->id)>
                        #{{ $ssa->id }} — {{ $ssa->primaryService?->name ?? 'Service' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">Therapist</label>
            <select name="therapist_id"
                class="border border-border rounded-lg px-3 py-2 text-sm min-w-[220px] focus:ring-2 focus:ring-primary focus:border-primary">
                <option value="">All</option>
                @foreach ($therapists as $therapist)
                    <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                        {{ $therapist->name }} ({{ $therapist->email }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Apply</button>
            <a href="{{ request()->url() }}?tab=schedule"
                class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">Reset</a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table id="schedulesTable" class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-muted/40">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Date</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Time</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Therapist</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">SSA</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Service</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">School</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Status</th>
                    <th class="px-3 py-2 text-left font-semibold text-foreground/70">Billing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($schedules as $schedule)
                    <tr>
                        <td class="px-3 py-2">{{ optional($schedule->schedule_date)->format('Y-m-d') }}</td>
                        <td class="px-3 py-2">
                            {{ optional($schedule->start_time)->format('H:i') }}
                            @if ($schedule->end_time)
                                - {{ $schedule->end_time->format('H:i') }}
                            @endif
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
            </tbody>
        </table>
    </div>

    <div>
        {{ $schedules->withQueryString()->links() }}
    </div>
</x-ui::card>
