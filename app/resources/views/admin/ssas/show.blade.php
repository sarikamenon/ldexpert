<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::show-header :title="$ssa->student->name . ' - ' . $ssa->primaryService->name"
        :back-url="route('admin.ssas.index')" back-label="Back to List"
        :edit-url="route('admin.ssas.edit', $ssa)" edit-label="Edit SSA">
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
                <x-ui::button class="change-status-btn" data-ssa-id="{{ $ssa->id }}" data-status="completed">
                    Mark as Complete
                </x-ui::button>
                <x-ui::button variant="danger" class="change-status-btn" data-ssa-id="{{ $ssa->id }}"
                    data-status="deactivated">
                    Deactivate
                </x-ui::button>
            @elseif ($ssa->status === \App\Enums\SSAStatus::PENDING)
                <span class="text-sm text-foreground/70">
                    @if (!$ssa->assignedTherapist)
                        Assign a therapist to activate
                    @else
                        Will activate when therapist is assigned
                    @endif
                </span>
            @elseif ($ssa->status === \App\Enums\SSAStatus::DEACTIVATED)
                @if ($ssa->canBeActivated())
                    <x-ui::button variant="success" class="change-status-btn" data-ssa-id="{{ $ssa->id }}"
                        data-status="active">
                        Activate
                    </x-ui::button>
                @else
                    <span class="text-sm text-foreground/70">
                        Assign a therapist to reactivate this SSA
                    </span>
                @endif
            @endif
        </x-slot>
    </x-ui::show-header>

    {{-- Tabs Navigation --}}
    @php
        $tabs = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'dashboard'])],
            ['key' => 'details', 'label' => 'Details', 'href' => route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'details'])],
            ['key' => 'assignment', 'label' => 'Assignment', 'href' => route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'assignment'])],
            ['key' => 'session_logs', 'label' => 'Session Logs', 'href' => route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs'])],
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
                                Authorized (THO) Hours
                                <x-ui::tooltip-icon content="Total hours authorized for this SSA based on the agreed service frequency and duration." />
                            </span>
                            <span class="font-semibold">{{ number_format($minutesSummary->thoMinutes / 60, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground/70 flex items-center gap-1">
                                Scheduled Hours
                                <x-ui::tooltip-icon content="Total hours scheduled on the calendar for this SSA, including both upcoming and completed sessions." />
                            </span>
                            <span class="font-semibold">{{ number_format($minutesSummary->scheduledMinutes / 60, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground/70 flex items-center gap-1">
                                Logged Hours
                                <x-ui::tooltip-icon content="Hours captured on submitted or approved session logs for this SSA, before final approval." />
                            </span>
                            <span class="font-semibold">{{ number_format($minutesSummary->loggedMinutes / 60, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-foreground/70 flex items-center gap-1">
                                Approved Hours
                                <x-ui::tooltip-icon content="Hours from approved session logs that count toward THO utilization for this SSA." />
                            </span>
                            <span class="font-semibold">{{ number_format($minutesSummary->approvedMinutes / 60, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 pt-2 border-t border-border">
                            <span class="font-medium flex items-center gap-1">
                                Progress
                                <x-ui::tooltip-icon content="Percentage of authorized (THO) hours that have been approved for this SSA." />
                            </span>
                            <span class="font-semibold text-primary">
                                {{ $minutesSummary->getApprovedUtilizationPercentage() }}% of THO used
                            </span>
                        </div>
                    @else
                        <div class="flex items-center justify-between">
                            <span class="text-foreground/70">Served Hours</span>
                            <span class="font-semibold">{{ number_format($ssa->served_hours, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-foreground/70">THO Hours</span>
                            <span class="font-semibold">{{ number_format($ssa->tho_hours, 2) }}</span>
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
        <x-ssa.overview-details :ssa="$ssa" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'assignment' && isset($assignmentHistory))
        <x-ui::card class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-foreground">Assignment History</h3>
                @if ($ssa->assignedTherapist)
                    <x-ui::button variant="danger" id="unassignTherapistBtn" data-ssa-id="{{ $ssa->id }}">
                        Unassign Therapist
                    </x-ui::button>
                @else
                    <x-ui::button id="assignTherapistBtn" data-ssa-id="{{ $ssa->id }}">
                        Assign Therapist
                    </x-ui::button>
                @endif
            </div>

            {{-- Data for therapist assignment AJAX --}}
            <script type="application/json" id="therapists-for-service-url">
                @json(route('admin.ssas.therapists-for-service'))
            </script>
            <script type="application/json" id="ssa-service-ids">
                @json(array_merge(
                    [$ssa->primary_service_id],
                    $ssa->additionalServices->pluck('id')->all()
                ))
            </script>

            @if ($assignmentHistory->count() > 0)
                <div class="space-y-4">
                    @foreach ($assignmentHistory as $history)
                        <div class="flex items-start gap-4 p-4 border border-border rounded-lg">
                            <div class="flex-shrink-0">
                                @if ($history->action === 'assigned')
                                    <div class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-10 h-10 rounded-full bg-danger/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
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
    @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogStatuses))
        <x-admin.session-logs-list :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []"
            :datatable-url="$datatableUrl ?? null" :ssa-id="$ssaId ?? null" context="detail" />
    @endif

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ssas-show.js'])
        @if (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/js/pages/admin-session-logs-index.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
