{{-- Session log status legend, shared by therapist and admin schedule calendars --}}
<div class="flex flex-wrap gap-2 items-center mb-4 p-3 rounded-lg border border-border bg-muted/40" aria-label="Session log status legend">
    <span class="text-xs font-semibold text-foreground/80 mr-1">Session log:</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-warning" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Pending submission
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-foreground/50" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Draft
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-danger" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
        </svg>
        Sent back
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
        </svg>
        Submitted
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Approved
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <svg class="w-4 h-4 text-foreground/40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Cancelled
    </span>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-foreground/80 px-2 py-1 rounded-md bg-background border border-border">
        <span class="legend-orphan-log-swatch" aria-hidden="true"></span>
        Log only (no schedule)
    </span>
</div>
