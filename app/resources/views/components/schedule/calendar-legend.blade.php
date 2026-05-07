{{-- Session log status legend, shared by therapist and admin schedule calendars --}}
<div class="flex flex-wrap gap-2 items-center mb-4 p-3 rounded-lg border border-border bg-muted/40" aria-label="Session log status legend">
    <span class="text-xs font-semibold text-foreground/80 mr-1">Session log:</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="pending" class="w-4 h-4 text-warning" />
        Pending submission
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="draft" class="w-4 h-4 text-foreground/50" />
        Draft
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="sent_back" class="w-4 h-4 text-danger" />
        Sent back
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="submitted" class="w-4 h-4 text-primary" />
        Submitted
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="approved" class="w-4 h-4 text-success" />
        Approved
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <x-ui::session-log-status-icon status="cancelled" class="w-4 h-4 text-foreground/40" />
        Cancelled
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <span class="legend-orphan-log-swatch" aria-hidden="true"></span>
        Log only (no schedule)
    </span>
</div>
