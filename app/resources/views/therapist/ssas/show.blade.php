<x-app-layout>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            {{-- Header Card --}}
            <x-ui::show-header :title="$ssa->student->name . ' - ' . $ssa->primaryService->name"
                :back-url="route('therapist.ssas.index')" back-label="Back to List">
                <x-slot name="badge">
                    <x-ui::badge :variant="match ($ssa->status) {
                        \App\Enums\SSAStatus::ACTIVE => 'success',
                        \App\Enums\SSAStatus::PENDING => 'warning',
                        \App\Enums\SSAStatus::COMPLETED => 'primary',
                        \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                        default => 'secondary',
                    }">
                        {{ $ssa->status->label() }}
                    </x-ui::badge>
                </x-slot>
                <x-slot name="actions">
                    @if ($ssa->status === \App\Enums\SSAStatus::ACTIVE)
                        <a href="{{ route('therapist.schedule.create', ['ssa_id' => $ssa->id]) }}">
                            <x-ui::button>
                                + Add New Schedule
                            </x-ui::button>
                        </a>
                        <a href="{{ route('therapist.session-logs.create', ['ssa_id' => $ssa->id]) }}">
                            <x-ui::button variant="success">
                                + Add Session Log
                            </x-ui::button>
                        </a>
                    @endif
                </x-slot>
            </x-ui::show-header>

            {{-- Tabs Navigation --}}
            @php
                $tabs = [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'dashboard'])],
                    ['key' => 'details', 'label' => 'Details', 'href' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'details'])],
                    ['key' => 'assignment', 'label' => 'Assignment History', 'href' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'assignment'])],
                    ['key' => 'session_logs', 'label' => 'Session Logs', 'href' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs'])],
                ];
            @endphp
            <x-ui::tabs :tabs="$tabs" :active-tab="$activeTab ?? 'dashboard'" />

            {{-- Tab Content --}}
            @if (($activeTab ?? 'dashboard') === 'dashboard')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    {{-- Delivery Progress Chart --}}
                    <x-ui::card class="p-6 lg:col-span-1">
                        <h3 class="text-lg font-semibold text-foreground mb-4">Delivery Progress</h3>
                        <div class="relative" style="height: 250px;">
                            <canvas
                                id="deliveryProgressChart"
                                data-served="{{ $ssa->served_minutes }}"
                                data-tho="{{ $ssa->tho_minutes }}"
                                @isset($minutesSummary)
                                    data-scheduled="{{ $minutesSummary->scheduledMinutes }}"
                                    data-logged="{{ $minutesSummary->loggedMinutes }}"
                                    data-approved="{{ $minutesSummary->approvedMinutes }}"
                                @endisset
                            ></canvas>
                        </div>
                        <div class="mt-4 space-y-2 text-sm" aria-label="Minutes ledger">
                            @isset($minutesSummary)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-foreground/70 flex items-center gap-1">
                                        Authorized (THO) Minutes
                                        <x-ui::tooltip-icon content="Total minutes authorized for this SSA based on the agreed service frequency and duration." />
                                    </span>
                                    <span class="font-semibold">{{ number_format($minutesSummary->thoMinutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-foreground/70 flex items-center gap-1">
                                        Scheduled Minutes
                                        <x-ui::tooltip-icon content="Total minutes scheduled on the calendar for this SSA, including both upcoming and completed sessions." />
                                    </span>
                                    <span class="font-semibold">{{ number_format($minutesSummary->scheduledMinutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-foreground/70 flex items-center gap-1">
                                        Logged Minutes
                                        <x-ui::tooltip-icon content="Minutes captured on submitted or approved session logs for this SSA, before final approval." />
                                    </span>
                                    <span class="font-semibold">{{ number_format($minutesSummary->loggedMinutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-foreground/70 flex items-center gap-1">
                                        Approved Minutes
                                        <x-ui::tooltip-icon content="Minutes from approved session logs that count toward THO utilization for this SSA." />
                                    </span>
                                    <span class="font-semibold">{{ number_format($minutesSummary->approvedMinutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 pt-2 border-t border-border">
                                    <span class="font-medium flex items-center gap-1">
                                        Progress
                                        <x-ui::tooltip-icon content="Percentage of authorized (THO) minutes that have been approved for this SSA." />
                                    </span>
                                    <span class="font-semibold text-primary">
                                        {{ $minutesSummary->getApprovedUtilizationPercentage() }}% of THO used
                                    </span>
                                </div>
                            @else
                                <div class="flex items-center justify-between">
                                    <span class="text-foreground/70">Served Minutes</span>
                                    <span class="font-semibold">{{ number_format($ssa->served_minutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-foreground/70">THO Minutes</span>
                                    <span class="font-semibold">{{ number_format($ssa->tho_minutes) }}</span>
                                </div>
                                <div class="flex items-center justify-between pt-2 border-t border-border">
                                    <span class="font-medium">Progress</span>
                                    <span class="font-semibold text-primary">
                                        {{ $ssa->tho_minutes > 0 ? number_format(($ssa->served_minutes / $ssa->tho_minutes) * 100, 1) : 0 }}%
                                    </span>
                                </div>
                            @endisset
                        </div>
                    </x-ui::card>

                    {{-- Quick Stats --}}
                    <x-ssa.dashboard-stats :ssa="$ssa" />
                </div>

            @elseif (($activeTab ?? 'dashboard') === 'details')
                <x-ssa.overview-details :ssa="$ssa" context="therapist" />
            @elseif (($activeTab ?? 'dashboard') === 'assignment' && isset($assignmentHistory))
                <x-ui::card class="p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-foreground">Assignment History</h3>

                    @if ($assignmentHistory->count() > 0)
                        <div class="space-y-4">
                            @foreach ($assignmentHistory as $history)
                                <div class="flex items-start gap-4 p-4 border border-border rounded-lg">
                                    <div class="flex-shrink-0">
                                        @if ($history->action === 'assigned')
                                            <div
                                                class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                        @else
                                            <div
                                                class="w-10 h-10 rounded-full bg-danger/20 flex items-center justify-center">
                                                <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                    </path>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-medium">
                                                @if ($history->action === 'assigned')
                                                    Assigned to {{ $history->therapist->name ?? 'Unknown' }}
                                                @else
                                                    Unassigned from {{ $history->therapist->name ?? 'Unknown' }}
                                                @endif
                                            </span>
                                            <span class="text-sm text-foreground/60">
                                                {{ ($history->created_at_local ?? $history->created_at)->format('M d, Y g:i A') }}
                                            </span>
                                        </div>
                                        @if ($history->reason)
                                            <p class="text-sm text-foreground/70">{{ $history->reason }}</p>
                                        @endif
                                        <p class="text-xs text-foreground/50 mt-1">
                                            By {{ $history->assignedBy->name ?? 'Unknown' }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
            @else
                <p class="text-foreground/70 text-center py-4">No assignment history available.</p>
            @endif
        </x-ui::card>
    @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogs))
        <x-admin.session-logs-list :sessionLogs="$sessionLogs" :columns="$sessionLogColumns ?? []" :rows="$sessionLogRows ?? []"
            :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []" context="detail" />
    @endif
</div>
</div>

<x-slot name="scripts">
    @if (($activeTab ?? 'dashboard') === 'session_logs')
        @vite(['resources/js/pages/therapist-session-logs-index.js'])
    @else
        @vite(['resources/js/pages/therapist-ssas-show.js'])
    @endif
</x-slot>
</x-app-layout>
