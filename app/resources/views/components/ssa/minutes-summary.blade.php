@props(['ssa', 'summary'])

<x-ui::card class="p-6 lg:col-span-2">
    <h3 class="text-lg font-semibold text-foreground mb-4">Minutes Overview</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div>
            <p class="text-xs font-medium text-foreground/70">Authorized (THO) Minutes</p>
            <p class="mt-1 text-xs text-foreground/60" id="tho_minutes_help">
                Total minutes authorized for this SSA based on the agreed service frequency and duration.
            </p>
            <p class="mt-2 text-sm font-semibold text-foreground" aria-describedby="tho_minutes_help">
                {{ number_format($summary->thoMinutes) }}
            </p>
        </div>

        <div>
            <p class="text-xs font-medium text-foreground/70">Scheduled Minutes</p>
            <p class="mt-1 text-xs text-foreground/60" id="scheduled_minutes_help">
                Total minutes scheduled on the calendar for this SSA, including both upcoming and completed sessions.
            </p>
            <p class="mt-2 text-sm font-semibold text-foreground" aria-describedby="scheduled_minutes_help">
                {{ number_format($summary->scheduledMinutes) }}
            </p>
        </div>

        <div>
            <p class="text-xs font-medium text-foreground/70">Logged Minutes</p>
            <p class="mt-1 text-xs text-foreground/60" id="logged_minutes_help">
                Minutes captured on submitted or approved session logs for this SSA, before final approval.
            </p>
            <p class="mt-2 text-sm font-semibold text-foreground" aria-describedby="logged_minutes_help">
                {{ number_format($summary->loggedMinutes) }}
            </p>
        </div>

        <div>
            <p class="text-xs font-medium text-foreground/70">Approved Minutes</p>
            <p class="mt-1 text-xs text-foreground/60" id="approved_minutes_help">
                Minutes from approved session logs that count toward THO utilization for this SSA.
            </p>
            <p class="mt-2 text-sm font-semibold text-foreground" aria-describedby="approved_minutes_help">
                {{ number_format($summary->approvedMinutes) }}
            </p>
            <p class="mt-1 text-xs text-foreground/60">
                {{ $summary->getApprovedUtilizationPercentage() }}% of THO used
            </p>
        </div>
    </div>
</x-ui::card>

