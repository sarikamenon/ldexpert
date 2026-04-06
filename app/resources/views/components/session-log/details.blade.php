@props(['sessionLog'])

<x-ui::card class="p-6 space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h3 class="text-sm font-semibold text-foreground/70 mb-3">Session Information</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Session Date</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->session_date?->format('M d, Y') ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Start Time</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->start_time?->format('g:i A') ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">End Time</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->end_time?->format('g:i A') ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Duration</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->duration_minutes ? $sessionLog->duration_minutes . ' minutes' : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Outcome</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->outcome?->label() ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-foreground/70 mb-3">Student &amp; Billing</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Student</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->student?->name ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Service</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->service?->name ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-foreground/60">Therapist Amount</dt>
                    <dd class="text-foreground font-medium">
                        {{ $sessionLog->therapist_billable_amount ?? '—' }}
                    </dd>
                </div>
                @if (auth()->check() && auth()->user()->role === \App\Enums\Role::ADMIN)
                    <div class="flex justify-between gap-4">
                        <dt class="text-foreground/60">School/Family Amount</dt>
                        <dd class="text-foreground font-medium">
                            {{ $sessionLog->school_invoice_amount ?? '—' }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-foreground/70 mb-2">Session Notes</h3>
        <div
            class="rounded-lg border border-border bg-background/subtle px-4 py-3 text-sm text-foreground whitespace-pre-wrap"
            >{{ $sessionLog->notes ? trim((string) $sessionLog->notes) : 'No notes recorded for this session.' }}
        </div>
    </div>
</x-ui::card>
