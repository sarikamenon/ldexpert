@if ($invoice->emailLogs->isNotEmpty())
    <x-ui::card class="p-6 mb-6">
        <h2 class="text-lg font-semibold text-foreground mb-4">Email History</h2>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-border">
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Date/Time</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Type</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Recipient</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Sent By</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->emailLogs->sortByDesc('sent_at') as $log)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4 text-sm">{{ $log->sent_at->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-4 text-sm">
                                <x-ui::badge :variant="$log->type === \App\Enums\InvoiceEmailType::INITIAL ? 'primary' : 'secondary'">
                                    {{ $log->type->label() }}
                                </x-ui::badge>
                            </td>
                            <td class="py-3 px-4 text-sm">{{ $log->recipient_email }}</td>
                            <td class="py-3 px-4 text-sm">{{ $log->sentBy?->name ?? '—' }}</td>
                            <td class="py-3 px-4 text-sm">{{ Str::limit($log->custom_message ?? '—', 50) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui::card>
@endif
