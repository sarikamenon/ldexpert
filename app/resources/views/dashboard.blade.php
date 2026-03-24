<x-app-layout>
    {{-- Schedule Details Modal (for view icon on Today's Schedule cards) --}}
    <x-schedule.schedule-details-modal />

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 space-y-8">
            @php
                $submittedMinutes = (int) ($submittedMinutesThisWeek ?? 0);
                $submittedHours = intdiv($submittedMinutes, 60);
                $submittedMinutesRemainder = $submittedMinutes % 60;
                $submittedMinutesLabel = match (true) {
                    $submittedHours > 0 && $submittedMinutesRemainder > 0 => "{$submittedHours}h {$submittedMinutesRemainder}m",
                    $submittedHours > 0 => "{$submittedHours}h",
                    default => "{$submittedMinutes}m",
                };
            @endphp

            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="block">
                    <x-dashboard::metric :title="'Active Students'" :value="$activeStudents ?? 0">
                        <x-slot name="badge">+{{ $newStudentsThisMonth ?? 0 }} this month</x-slot>
                    </x-dashboard::metric>
                </div>
                <x-dashboard::metric :title="'This Week\'s Schedules'" :value="$lessonsThisWeek ?? 0">
                    <x-slot name="badge">
                        <span class="text-xs text-foreground/60">
                            {{ $lessonsToday ?? 0 }} today
                            @if (($pendingScheduleCount ?? 0) > 0)
                                • {{ $pendingScheduleCount }} pending
                            @endif
                        </span>
                    </x-slot>
                </x-dashboard::metric>
                <x-dashboard::metric :title="'SSA'" :value="$activeSSAs ?? 0">
                    <x-slot name="badge">
                        <span class="text-xs text-foreground/60">
                            {{ $completedSSAs ?? 0 }} completed
                        </span>
                    </x-slot>
                </x-dashboard::metric>
                <x-dashboard::metric :title="'Session Time Submitted This Week'" :value="$submittedMinutesLabel">
                    <x-slot name="badge">
                        <span class="text-xs text-foreground/60">
                            {{ $submittedSessionsThisWeek ?? 0 }} submitted
                        </span>
                    </x-slot>
                </x-dashboard::metric>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Schedule -->
                <x-dashboard::schedule title="Today's Schedule" :view-all-url="route('therapist.schedule.calendar', ['date' => now()->format('Y-m-d')])">
                    @forelse($todaySchedules ?? [] as $schedule)
                        <x-schedule.schedule-list-item :schedule="$schedule" class="mb-3" />
                    @empty
                        <div class="text-sm text-foreground/60">
                            No schedules for today.
                        </div>
                    @endforelse
                </x-dashboard::schedule>

                <!-- Sessions to rectify -->
                <x-ui::card>
                    <div class="p-5 border-b border-border flex items-center justify-between">
                        <h3 class="text-lg font-medium text-foreground">Sessions to rectify</h3>
                        <a href="{{ route('therapist.session-logs.index') }}" class="text-sm text-accent hover:underline">View all</a>
                    </div>
                    <div class="p-5 space-y-4">
                        @forelse($sentBackSessionLogs ?? [] as $log)
                            <div class="rounded-lg border border-border p-4 bg-background">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-foreground">
                                            {{ $log->student?->name ?? 'N/A' }}
                                        </div>
                                        <div class="text-sm text-foreground/70 mt-1">
                                            {{ $log->service?->name ?? 'N/A' }} · {{ $log->session_date?->format('M d, Y') ?? '' }}
                                        </div>
                                        @if ($log->getLatestSentBackComment())
                                            <p class="text-xs text-foreground/60 mt-2 line-clamp-2">
                                                {{ Str::limit($log->getLatestSentBackComment()->comment, 80) }}
                                            </p>
                                        @endif
                                    </div>
                                    <a href="{{ route('therapist.session-logs.edit', $log) }}" class="shrink-0">
                                        <x-ui::button variant="primary" size="sm">Edit</x-ui::button>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-foreground/60">
                                <p>No sessions sent back for rectification.</p>
                            </div>
                        @endforelse
                    </div>
                </x-ui::card>

                <!-- Pending Schedules -->
                <x-ui::card>
                    <div class="p-5 border-b border-border flex items-center justify-between">
                        <h3 class="text-lg font-medium text-foreground">Pending Schedules</h3>
                        <a href="{{ route('therapist.schedule.pending') }}" class="text-sm text-accent hover:underline">View all</a>
                    </div>
                    <div class="p-5 space-y-4">
                        @forelse($pendingSchedulesList ?? [] as $row)
                            <div class="rounded-lg border border-border p-4 bg-background">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-foreground">
                                            {{ $row['student_name'] ?? $row['student'] ?? 'N/A' }}
                                        </div>
                                        <div class="text-sm text-foreground/70 mt-1">
                                            {{ $row['service'] ?? 'N/A' }} · {{ $row['schedule_date'] ?? '' }}
                                        </div>
                                    </div>
                                    @if (!empty($row['create_session_log_url']))
                                        <a href="{{ $row['create_session_log_url'] }}" class="shrink-0">
                                            <x-ui::button size="sm">Create session log</x-ui::button>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-foreground/60">
                                <p>No pending schedules.</p>
                            </div>
                        @endforelse
                    </div>
                </x-ui::card>

                <!-- My SSAs -->
                <x-ui::card>
                    <div class="p-5 border-b border-border flex items-center justify-between">
                        <h3 class="text-lg font-medium text-foreground">My SSAs</h3>
                        <a href="{{ route('therapist.ssas.index') }}" class="text-sm text-accent hover:underline">View
                            All</a>
                    </div>
                    <div class="p-5 space-y-4">
                        @forelse($ssasList ?? [] as $ssa)
                            <div class="rounded-lg border border-border p-4 bg-background">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="font-medium text-foreground">
                                                <a href="{{ route('therapist.students.show', $ssa->student_id) }}"
                                                    class="hover:underline text-foreground">
                                                    {{ $ssa->student->name ?? 'N/A' }}
                                                </a>
                                            </div>
                                            <x-ui::badge
                                                variant="{{ $ssa->status->value === 'active' ? 'primary' : ($ssa->status->value === 'completed' ? 'success' : 'muted') }}"
                                                class="shrink-0">
                                                {{ $ssa->status->label() }}
                                            </x-ui::badge>
                                        </div>
                                        <div class="text-sm text-foreground/70 mt-1">
                                            {{ $ssa->primaryService->name ?? 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="{{ route('therapist.ssas.show', $ssa->id) }}" class="flex-1">
                                        <x-ui::button variant="secondary" class="w-full" size="sm">
                                            View Detail
                                        </x-ui::button>
                                    </a>
                                    @if ($ssa->status === \App\Enums\SSAStatus::ACTIVE)
                                        <a href="{{ route('therapist.schedule.create', ['ssa_id' => $ssa->id]) }}"
                                            class="flex-1">
                                            <x-ui::button class="w-full" size="sm">
                                                Create Schedule
                                            </x-ui::button>
                                        </a>
                                    @else
                                        <div class="flex-1">
                                            <x-ui::button class="w-full" size="sm" disabled>
                                                Create Schedule
                                            </x-ui::button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-foreground/60">
                                <p>No SSAs assigned yet</p>
                            </div>
                        @endforelse
                    </div>
                </x-ui::card>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-schedule-calendar.js'])
    </x-slot>
</x-app-layout>
