<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Bill Your Schedule</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Past schedules that haven't been billed yet ({{ $pendingCount ?? 0 }} total)
                </p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border" id="pendingScheduleList">
                        <thead class="bg-muted/40">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Date &amp; Time
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Student, Service &amp; School
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-background divide-y divide-border">
                            @forelse ($pendingSchedules ?? [] as $schedule)
                                @php
                                    $durationMinutes = $schedule->durationMinutes();
                                    $hours = intdiv($durationMinutes, 60);
                                    $minutesRemainder = $durationMinutes % 60;
                                    $durationLabel =
                                        $hours > 0
                                            ? trim(
                                                $hours .
                                                    'h ' .
                                                    ($minutesRemainder > 0 ? $minutesRemainder . 'm' : ''),
                                            )
                                            : $minutesRemainder . 'm';

                                    $startTime = $schedule->start_time?->format('g:i A');
                                    $endTime = $schedule->end_time?->format('g:i A');
                                    $timeRange = $startTime && $endTime ? "{$startTime} - {$endTime}" : null;
                                @endphp
                                <tr data-schedule-id="{{ $schedule->id }}">
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex flex-col space-y-1">
                                            @if ($schedule->schedule_date)
                                                <span class="text-foreground font-medium">{{ $schedule->schedule_date->format('M d, Y') }}</span>
                                            @endif
                                            @if ($timeRange)
                                                <span class="text-foreground">{{ $timeRange }}</span>
                                            @endif
                                            @if ($durationLabel)
                                                <span class="text-xs text-foreground/60">{{ $durationLabel }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex flex-col">
                                            @if ($schedule->student)
                                                <span class="font-medium text-foreground">
                                                    <a href="{{ route('therapist.students.show', $schedule->student->id) }}"
                                                        class="hover:underline text-primary">
                                                        {{ $schedule->student->name }}
                                                    </a>
                                                </span>
                                            @endif
                                            @if ($schedule->service)
                                                <span class="text-sm text-foreground/70">{{ $schedule->service->name }}</span>
                                            @endif
                                            @if ($schedule->school)
                                                <span class="text-xs text-foreground/60 mt-1">{{ $schedule->school->display_name ?? $schedule->school->name }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button"
                                                class="schedule-delete-btn inline-flex items-center justify-center rounded-full border border-destructive/20 bg-background text-destructive hover:bg-destructive/10 hover:border-destructive/30 px-3 py-1 text-xs font-medium transition-colors">
                                                Delete Schedule
                                            </button>
                                            <button type="button"
                                                class="schedule-bill-btn inline-flex items-center justify-center rounded-full bg-primary text-primary-foreground hover:bg-primary/90 px-3 py-1 text-xs font-medium transition-colors"
                                                data-schedule-id="{{ $schedule->id }}">
                                                Bill Your Session
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center">
                                        <p class="text-foreground/70">No pending schedules found.</p>
                                        <p class="text-sm text-foreground/60 mt-2">
                                            All past schedules have been billed or marked appropriately.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>
    </div>
    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-schedule-pending.js'])
    </x-slot>
</x-app-layout>
