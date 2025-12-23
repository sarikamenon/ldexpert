<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <x-page-title title="{{ $ssa->student->name }} - {{ $ssa->primaryService->name }}" />
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                {{-- Status Badge --}}
                <x-ui::badge :variant="match ($ssa->status) {
                    \App\Enums\SSAStatus::ACTIVE => 'success',
                    \App\Enums\SSAStatus::PENDING => 'warning',
                    \App\Enums\SSAStatus::COMPLETED => 'primary',
                    \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                    default => 'secondary',
                }">
                    {{ $ssa->status->label() }}
                </x-ui::badge>

                {{-- Action Buttons --}}
                <a href="{{ route('admin.ssas.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                    Back to List
                </a>
                <a href="{{ route('admin.ssas.edit', $ssa) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Edit SSA
                </a>

                {{-- Status Change Buttons --}}
                @if ($ssa->status === \App\Enums\SSAStatus::ACTIVE)
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                        data-ssa-id="{{ $ssa->id }}" data-status="completed">
                        Mark as Complete
                    </button>
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium"
                        data-ssa-id="{{ $ssa->id }}" data-status="deactivated">
                        Deactivate
                    </button>
                @elseif ($ssa->status === \App\Enums\SSAStatus::PENDING)
                    <span class="text-sm text-foreground/70">
                        @if (!$ssa->assignedTherapist)
                            Assign a therapist to activate
                        @else
                            Will activate when therapist is assigned
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </x-ui::card>

    {{-- Tabs Navigation --}}
    <div class="border-b border-border mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'dashboard']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'details']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'details' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Details
            </a>
            <a href="{{ route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'assignment']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'assignment' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Assignment
            </a>
            <a href="{{ route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'session_logs']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'session_logs' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Session Logs
            </a>
        </nav>
    </div>

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- Delivery Progress Chart --}}
            <x-ui::card class="p-6 lg:col-span-1">
                <h3 class="text-lg font-semibold text-foreground mb-4">Delivery Progress</h3>
                <div class="relative" style="height: 250px;">
                    <canvas id="deliveryProgressChart" data-served="{{ $ssa->served_minutes }}"
                        data-tho="{{ $ssa->tho_minutes }}"></canvas>
                </div>
                <div class="mt-4 space-y-2 text-center">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-foreground/70">Served Minutes</span>
                        <span class="text-sm font-semibold">{{ number_format($ssa->served_minutes) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-foreground/70">THO Minutes</span>
                        <span class="text-sm font-semibold">{{ number_format($ssa->tho_minutes) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-border">
                        <span class="text-sm font-medium">Progress</span>
                        <span class="text-sm font-semibold text-primary">
                            {{ $ssa->tho_minutes > 0 ? number_format(($ssa->served_minutes / $ssa->tho_minutes) * 100, 1) : 0 }}%
                        </span>
                    </div>
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
                    <button type="button" id="unassignTherapistBtn"
                        class="inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium"
                        data-ssa-id="{{ $ssa->id }}">
                        Unassign Therapist
                    </button>
                @else
                    <button type="button" id="assignTherapistBtn"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                        data-ssa-id="{{ $ssa->id }}">
                        Assign Therapist
                    </button>
                @endif
            </div>

            {{-- Hidden select for therapist assignment --}}
            <select id="therapist_select_for_assignment" class="hidden">
                <option value="">Select a therapist</option>
                @foreach ($therapists ?? [] as $therapist)
                    <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
                @endforeach
            </select>

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
    @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogs))
        <x-admin.session-logs-list :sessionLogs="$sessionLogs" :columns="$sessionLogColumns ?? []" :rows="$sessionLogRows ?? []"
            :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []" context="detail" />
    @endif

    <x-slot name="scripts">
        @if (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/js/pages/admin-session-logs-index.js'])
        @else
            @vite(['resources/js/pages/admin-ssas-show.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
