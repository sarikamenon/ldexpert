<x-admin.layouts.app>
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Run History</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    {{ $schedule->schedule_type->label() }} —
                    {{ $schedule->schedulable?->display_name ?? $schedule->schedulable?->name ?? 'Entity #'.$schedule->schedulable_id }}
                </p>
            </div>
            <a href="{{ route('admin.billing.schedules.index') }}"
                class="inline-flex items-center gap-2 text-sm text-foreground/60 hover:text-foreground transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Schedules
            </a>
        </div>
    </div>

    <x-ui::card class="p-6">
        @if ($runs->isEmpty())
            <x-ui::empty-state class="py-12">
                <p class="text-foreground/60">No billing runs have been recorded yet.</p>
            </x-ui::empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Period</th>
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Generated</th>
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Sessions</th>
                            @if ($schedule->isAdvanceMode())
                                <th class="text-left py-3 px-2 font-medium text-foreground/70">Adjustments</th>
                                <th class="text-left py-3 px-2 font-medium text-foreground/70">Carry Fwd</th>
                            @else
                                <th class="text-left py-3 px-2 font-medium text-foreground/70">Late Entries</th>
                            @endif
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Total</th>
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Status</th>
                            <th class="text-left py-3 px-2 font-medium text-foreground/70">Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            <tr class="border-b border-border/50 hover:bg-muted/50">
                                <td class="py-3 px-2">
                                    {{ $run->billing_period_start->format('M d') }} – {{ $run->billing_period_end->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-2">{{ $run->generation_date->format('M d, Y') }}</td>
                                <td class="py-3 px-2">{{ $run->sessions_found }}</td>
                                @if ($schedule->isAdvanceMode())
                                    <td class="py-3 px-2">
                                        {{ $run->adjustments_count }}
                                        @if ((float) $run->adjustment_total != 0)
                                            <span class="text-xs text-foreground/50">({{ $run->adjustment_total >= 0 ? '+' : '' }}${{ number_format((float) $run->adjustment_total, 2) }})</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-2">
                                        @if ((float) $run->carry_forward_amount > 0)
                                            ${{ number_format((float) $run->carry_forward_amount, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                @else
                                    <td class="py-3 px-2">{{ $run->sessions_from_prior_periods }}</td>
                                @endif
                                <td class="py-3 px-2">
                                    @if ($run->total_amount !== null)
                                        ${{ number_format((float) $run->total_amount, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-2">
                                    @if ($run->isSuccess())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-success/10 text-success border border-success/20">Success</span>
                                    @elseif ($run->wasSkipped())
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-secondary border border-secondary/20">Skipped</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-danger/10 text-danger border border-danger/20">Failed</span>
                                    @endif
                                </td>
                                <td class="py-3 px-2">
                                    @if ($run->invoice_id && $run->invoice)
                                        <a href="{{ route('admin.invoices.show', $run->invoice_id) }}" class="text-primary hover:underline">
                                            {{ $run->invoice->invoice_number }}
                                        </a>
                                    @elseif ($run->therapist_bill_id && $run->therapistBill)
                                        <a href="{{ route('admin.billing.therapist-bills.show', $run->therapist_bill_id) }}" class="text-primary hover:underline">
                                            {{ $run->therapistBill->bill_number }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>
