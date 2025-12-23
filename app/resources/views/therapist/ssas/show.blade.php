<x-app-layout>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            {{-- Header Card --}}
            <x-ui::card class="p-6 mb-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">
                            {{ $ssa->student->name }} - {{ $ssa->primaryService->name }}
                        </h1>
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

                        {{-- Add Schedule Button (only for active SSAs) --}}
                        @if ($ssa->status === \App\Enums\SSAStatus::ACTIVE)
                            <a href="{{ route('therapist.schedule.create', ['ssa_id' => $ssa->id]) }}"
                                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                                + Add New Schedule
                            </a>
                            <a href="{{ route('therapist.session-logs.create', ['ssa_id' => $ssa->id]) }}"
                                class="inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium">
                                + Add Session Log
                            </a>
                        @endif

                        {{-- Back Button --}}
                        <a href="{{ route('therapist.ssas.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                            Back to List
                        </a>
                    </div>
                </div>
            </x-ui::card>

            {{-- Tabs Navigation --}}
            <div class="border-b border-border mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'dashboard']) }}"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'details']) }}"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'details' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                        Details
                    </a>
                    <a href="{{ route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'assignment']) }}"
                        class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'assignment' ? 'border-primary text-primary font-medium' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                        Assignment History
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
            @endif
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-ssas-show.js'])
    </x-slot>
</x-app-layout>
