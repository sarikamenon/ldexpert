{{-- @var array<string, mixed> $detail --}}

{{-- Header --}}
<div class="flex items-start justify-between gap-3 px-6 pt-6 pb-5 border-b border-border">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-3 flex-wrap">
            <h2 class="text-xl font-semibold text-foreground">Make-up request</h2>
            <x-ui::badge :variant="$detail['status_variant']">{{ $detail['status_label'] }}</x-ui::badge>
        </div>
        <div class="mt-2 flex items-center gap-2 text-sm text-foreground/70">
            <span class="text-foreground/50">
                <x-therapist.makeup-requests.icon name="calendar" class="h-4 w-4 shrink-0" />
            </span>
            <span>{{ $detail['closure_title'] }} · {{ $detail['event_date'] }}</span>
        </div>
    </div>
    <button
        type="button"
        class="shrink-0 text-foreground/40 hover:text-foreground rounded-md p-1 -m-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        x-on:click="$dispatch('close-modal', 'makeup-request-detail')"
        aria-label="Close">
        <x-therapist.makeup-requests.icon name="x" class="h-5 w-5" />
    </button>
</div>

{{-- Student strip --}}
<div class="px-6 py-4 border-b border-border">
    <div class="flex items-center gap-3">
        <div class="shrink-0 w-11 h-11 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-semibold tracking-wide">
            {{ $detail['student_initials'] }}
        </div>
        <div class="min-w-0">
            <p class="text-base font-semibold text-foreground leading-tight truncate">
                {{ $detail['student_name'] }}
            </p>
            <p class="mt-0.5 text-sm text-foreground/60 truncate">
                {{ $detail['service_meta'] }}
            </p>
        </div>
    </div>
</div>

{{-- Body --}}
<div class="px-6 py-5">
    @switch($detail['status'])
        @case('pending')
            <p class="text-xs font-medium uppercase tracking-wider text-foreground/60 mb-3">What happens next</p>
            <div class="space-y-2">
                <x-therapist.makeup-requests.event-card
                    tone="neutral"
                    icon="mail"
                    title="Reminder email sent to parent"
                    sub="Parent can request make-up via the email link"
                    :date="$detail['reminder_date']"
                    :rel="$detail['reminder_date_relative']" />
                <x-therapist.makeup-requests.event-card
                    tone="warning"
                    icon="warning"
                    title="Auto-declines if no response"
                    sub="Parent must reply by the response date"
                    :date="$detail['response_date']"
                    :rel="$detail['response_date_relative']" />
            </div>
            <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
                <span class="text-foreground/40 mt-0.5">
                    <x-therapist.makeup-requests.icon name="info" class="h-4 w-4 shrink-0" />
                </span>
                <span>You can decline this make-up manually before the reminder is sent if it's no longer needed.</span>
            </p>
            @break

        @case('sent')
            <p class="text-xs font-medium uppercase tracking-wider text-foreground/60 mb-3">What happens next</p>
            <div class="space-y-2">
                <x-therapist.makeup-requests.event-card
                    tone="warning"
                    icon="warning"
                    title="Auto-declines if no response"
                    sub="Parent must reply by the response date"
                    :date="$detail['response_date']"
                    :rel="$detail['response_date_relative']" />
            </div>
            <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
                <span class="text-foreground/40 mt-0.5">
                    <x-therapist.makeup-requests.icon name="info" class="h-4 w-4 shrink-0" />
                </span>
                <span>Reminder sent to parent on {{ $detail['reminder_sent_at_short'] ?? $detail['reminder_date'] }}. Waiting for their response.</span>
            </p>
            @break

        @case('requested')
            <div class="rounded-lg bg-success/10 border border-success/15 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-success mt-0.5">
                        <x-therapist.makeup-requests.icon name="user-check" class="h-5 w-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-success">Parent requested this make-up</p>
                        <p class="mt-1 text-sm text-success/80">
                            {{ $detail['responded_at_short'] ?? $detail['response_date'] }}
                            · in response to reminder sent {{ $detail['reminder_sent_at_short'] ?? $detail['reminder_date'] }}
                        </p>
                    </div>
                </div>
            </div>
            <p class="mt-4 flex items-center gap-2 text-sm text-foreground/70">
                <x-therapist.makeup-requests.icon name="arrow-right" class="h-4 w-4 shrink-0" />
                <span>Schedule a session to complete the make-up</span>
            </p>
            @break

        @case('declined')
            <div class="rounded-lg bg-danger/10 border border-danger/15 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-danger mt-0.5">
                        <x-therapist.makeup-requests.icon name="x-circle" class="h-5 w-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-danger">{{ $detail['decline_banner_title'] }}</p>
                        <p class="mt-1 text-sm text-danger/80">{{ $detail['decline_banner_sub'] }}</p>
                    </div>
                </div>
            </div>
            <div class="mt-4 space-y-2">
                @if ($detail['reminder_sent_at_short'])
                    <p class="flex items-start gap-2 text-sm text-foreground/70">
                        <x-therapist.makeup-requests.icon name="mail" class="h-5 w-5 shrink-0" />
                        <span>Reminder sent to parent on {{ $detail['reminder_sent_at_short'] }}</span>
                    </p>
                @endif
                @if ($detail['is_auto_decline'])
                    <p class="flex items-start gap-2 text-sm text-foreground/70">
                        <x-therapist.makeup-requests.icon name="clock-slash" class="h-4 w-4 shrink-0" />
                        <span>5-day response window closed without action</span>
                    </p>
                @endif
                @if ($detail['reason'])
                    <p class="flex items-start gap-2 text-sm text-foreground/70">
                        <x-therapist.makeup-requests.icon name="info" class="h-4 w-4 shrink-0" />
                        <span>Reason: {{ $detail['reason'] }}</span>
                    </p>
                @endif
            </div>
            @break

        @case('scheduled')
            @if ($detail['makeup_schedule'])
                @if ($detail['makeup_schedule']['id'])
                    <button type="button" data-schedule-id="{{ $detail['makeup_schedule']['id'] }}"
                        class="block w-full text-left rounded-lg bg-success/10 border border-success/15 p-5 hover:bg-success/15 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-success/40">
                        <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-success">
                            <x-therapist.makeup-requests.icon name="calendar" class="h-5 w-5 shrink-0" />
                            Make-up session
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-success leading-tight">{{ $detail['makeup_schedule']['date'] }}</p>
                        @if ($detail['makeup_schedule']['meta'])
                            <p class="mt-1 text-sm text-success/80">{{ $detail['makeup_schedule']['meta'] }}</p>
                        @endif
                        <p class="mt-3 text-xs font-medium text-success/80">View session details →</p>
                    </button>
                @else
                    <div class="rounded-lg bg-success/10 border border-success/15 p-5">
                        <p class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-success">
                            <x-therapist.makeup-requests.icon name="calendar" class="h-5 w-5 shrink-0" />
                            Make-up session
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-success leading-tight">{{ $detail['makeup_schedule']['date'] }}</p>
                        @if ($detail['makeup_schedule']['meta'])
                            <p class="mt-1 text-sm text-success/80">{{ $detail['makeup_schedule']['meta'] }}</p>
                        @endif
                    </div>
                @endif
            @else
                <p class="text-sm text-foreground/70">Make-up session scheduled, but details are unavailable.</p>
            @endif
            @break

        @case('failed')
            <div class="rounded-lg bg-danger/10 border border-danger/15 p-4">
                <div class="flex items-start gap-3">
                    <span class="text-danger mt-0.5">
                        <x-therapist.makeup-requests.icon name="x-circle" class="h-5 w-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-danger">Reminder email failed to send</p>
                        <p class="mt-1 text-sm text-danger/80">The parent reminder couldn't be delivered. Contact the parent directly or try again.</p>
                    </div>
                </div>
            </div>
            <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
                <span class="text-foreground/40 mt-0.5">
                    <x-therapist.makeup-requests.icon name="info" class="h-4 w-4 shrink-0" />
                </span>
                <span>Originally scheduled to send on {{ $detail['reminder_date'] }}.</span>
            </p>
            @break

        @case('not_required')
            <div class="rounded-lg bg-muted/60 border border-border p-4">
                <div class="flex items-start gap-3">
                    <span class="text-foreground/60 mt-0.5">
                        <x-therapist.makeup-requests.icon name="info" class="h-5 w-5 shrink-0" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-foreground">Marked as not required</p>
                        <p class="mt-1 text-sm text-foreground/70">The original session is still happening for this student, so no make-up is needed. No reminder was sent to the parent.</p>
                        @if ($detail['reason'])
                            <p class="mt-2 flex items-start gap-2 text-sm text-foreground/70">
                                <x-therapist.makeup-requests.icon name="info" class="h-4 w-4 shrink-0" />
                                <span>Reason: {{ $detail['reason'] }}</span>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <p class="mt-4 flex items-start gap-2 text-sm text-foreground/60">
                <span class="text-foreground/40 mt-0.5">
                    <x-therapist.makeup-requests.icon name="calendar" class="h-4 w-4 shrink-0" />
                </span>
                <span>Closure date was {{ $detail['event_date'] }}.</span>
            </p>
            @break

        @default
            <p class="text-sm text-foreground/70">Status: {{ $detail['status_label'] }}</p>
    @endswitch
</div>

{{-- Footer --}}
<div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-border">
    <div class="flex-1 flex items-center gap-2">
        @if ($detail['status'] === 'pending' && $detail['can_decline'])
            <button
                type="button"
                data-makeup-decline-url="{{ $detail['decline_url'] }}"
                class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg border border-border text-danger hover:bg-danger/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <x-therapist.makeup-requests.icon name="x" class="h-4 w-4 shrink-0" />
                Decline manually
            </button>
        @endif
        @if ($detail['status'] === 'pending' && $detail['can_mark_not_required'])
            <button
                type="button"
                data-makeup-not-required-url="{{ $detail['mark_not_required_url'] }}"
                class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg border border-border text-foreground/70 hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <x-therapist.makeup-requests.icon name="minus-circle" class="h-4 w-4 shrink-0" />
                Not required
            </button>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <button
            type="button"
            x-on:click="$dispatch('close-modal', 'makeup-request-detail')"
            class="inline-flex items-center gap-2 text-sm font-medium px-5 py-2 rounded-lg border border-border text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            Close
        </button>
        @if ($detail['status'] === 'requested' && $detail['can_book'])
            <a
                href="{{ $detail['book_url'] }}"
                class="inline-flex items-center gap-2 text-sm font-medium px-5 py-2 rounded-lg bg-foreground text-background hover:bg-foreground/90 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                <x-therapist.makeup-requests.icon name="calendar" class="h-4 w-4 shrink-0" />
                Schedule session
            </a>
        @endif
    </div>
</div>
