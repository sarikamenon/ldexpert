<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">Pending Schedule</h1>
                <p class="text-sm text-foreground/70 mt-1">
                    Past schedules that haven't been billed yet ({{ $pendingCount ?? 0 }} total)
                </p>
            </div>

            @if (($pendingSchedules ?? collect())->isEmpty())
                <x-ui::card class="p-6">
                    <div class="text-center py-12">
                        <p class="text-foreground/70">No pending schedules found.</p>
                        <p class="text-sm text-foreground/60 mt-2">
                            All past schedules have been billed or marked appropriately.
                        </p>
                    </div>
                </x-ui::card>
            @else
                <div class="space-y-4" id="pendingScheduleList">
                    @foreach ($pendingSchedules as $schedule)
                        <x-schedule.schedule-card
                            :schedule="[
                                'id' => $schedule->id,
                                'start_time' => $schedule->start_time?->format('g:i A'),
                                'end_time' => $schedule->end_time?->format('g:i A'),
                                'school' => $schedule->school?->display_name ?? 'N/A',
                                'student' => $schedule->student?->name ?? 'N/A',
                                'service' => $schedule->service?->name ?? 'N/A',
                                'schedule_date' => $schedule->schedule_date?->format('M j, Y'),
                                'status' => $schedule->status?->label(),
                                'billing_status' => $schedule->billing_status?->label(),
                                'notes' => $schedule->notes,
                                'is_group' => $schedule->is_group,
                            ]"
                            :show-actions="true"
                            :show-notes="true"
                        />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-schedule-pending.js'])
    </x-slot>
</x-app-layout>
