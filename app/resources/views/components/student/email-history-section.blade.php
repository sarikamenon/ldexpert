@props(['student', 'emailLogs'])

<x-ui::card class="p-6">
    <h2 class="text-lg font-semibold text-foreground mb-4">Email History</h2>
    @if ($emailLogs->isEmpty())
        <p class="text-sm text-foreground/60">No emails have been sent for this student's schedules yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Sent At</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Type</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Schedule</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Recipient</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Sent By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($emailLogs as $log)
                        <tr class="border-b border-border last:border-0">
                            <td class="py-3 px-4 text-sm">{{ $log->sent_at_formatted }}</td>
                            <td class="py-3 px-4 text-sm">
                                <x-ui::badge variant="{{ in_array($log->type->value, ['notification_created', 'notification_updated']) ? 'primary' : 'secondary' }}">
                                    {{ $log->type->label() }}
                                </x-ui::badge>
                            </td>
                            <td class="py-3 px-4 text-sm">
                                @if ($log->schedule)
                                    <div class="flex flex-col gap-0.5">
                                        <a href="{{ route('admin.students.show', ['student' => $student, 'tab' => 'schedule']) }}"
                                           class="text-primary hover:underline font-medium text-sm leading-tight">
                                            {{ $log->schedule->therapist?->name ?? '—' }}
                                            @if ($log->schedule->service)
                                                &middot; {{ $log->schedule->service->name }}
                                            @endif
                                        </a>
                                        <span class="text-xs text-foreground/60">{{ $log->schedule_local_date }}</span>
                                    </div>
                                @else
                                    <span class="text-foreground/40">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm">{{ $log->recipient_email }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->sentBy?->name ?? 'System' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui::card>
