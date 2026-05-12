@props([
    'goalMetrics' => [],
    'goalsTabUrl' => null,
])

@php
    $masteryRate = max(0, min(100, (float) ($goalMetrics['mastery_rate'] ?? 0)));
    $masteryDisplay = rtrim(rtrim(number_format($masteryRate, 1), '0'), '.');
    $total = (int) ($goalMetrics['total_goals'] ?? 0);
    $active = (int) ($goalMetrics['active_goals'] ?? 0);
    $mastered = (int) ($goalMetrics['mastered_goals'] ?? 0);
    $discontinued = (int) ($goalMetrics['discontinued_goals'] ?? 0);
    $ssaGap = (int) ($goalMetrics['ssas_without_active_goals'] ?? 0);
@endphp

<x-ui::card {{ $attributes->merge(['class' => 'p-6']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4 mb-4">
        <div>
            <h3 class="text-lg font-semibold text-foreground">Goals Snapshot</h3>
            <p class="mt-1 text-xs text-foreground/60" id="goals-snapshot-help">
                Counts across all SSAs for this student. SSAs w/o goals counts agreements with no active goal.
            </p>
        </div>
        @if ($goalsTabUrl)
            <a href="{{ $goalsTabUrl }}"
                class="inline-flex shrink-0 items-center justify-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-primary shadow-sm transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background active:bg-muted/80"
                aria-describedby="goals-snapshot-help">
                View all goals
                <svg xmlns="http://www.w3.org/2000/svg" class="ml-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" />
                </svg>
            </a>
        @endif
    </div>

    <div class="rounded-xl border border-border bg-muted/25 p-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="group flex min-h-[5.5rem] flex-col justify-between rounded-lg border border-border bg-card p-3 shadow-sm transition-colors hover:border-primary/25 hover:bg-muted/30 focus-within:border-primary/25">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-foreground/70">Total</span>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-foreground/70 ring-1 ring-border transition-transform group-hover:scale-105">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                        </svg>
                    </span>
                </div>
                <p class="text-2xl font-semibold tabular-nums tracking-tight text-foreground">{{ $total }}</p>
            </div>

            <div class="group flex min-h-[5.5rem] flex-col justify-between rounded-lg border border-border bg-card p-3 shadow-sm transition-colors hover:border-success/30 hover:bg-success/5 focus-within:border-success/30">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-foreground/70">Active</span>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-success/15 text-success ring-1 ring-success/20 transition-transform group-hover:scale-105">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h5l2 5 4-10 2 5h5" />
                        </svg>
                    </span>
                </div>
                <p class="text-2xl font-semibold tabular-nums tracking-tight text-success">{{ $active }}</p>
            </div>

            <div class="group flex min-h-[5.5rem] flex-col justify-between rounded-lg border border-border bg-card p-3 shadow-sm transition-colors hover:border-primary/30 hover:bg-primary/5 focus-within:border-primary/30">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-foreground/70">Mastered</span>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/15 text-primary ring-1 ring-primary/20 transition-transform group-hover:scale-105">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                </div>
                <p class="text-2xl font-semibold tabular-nums tracking-tight text-primary">{{ $mastered }}</p>
            </div>

            <div class="group flex min-h-[5.5rem] flex-col justify-between rounded-lg border border-border bg-card p-3 shadow-sm transition-colors hover:border-warning/30 hover:bg-warning/5 focus-within:border-warning/30">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-foreground/70">Discontinued</span>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-warning/15 text-warning ring-1 ring-warning/20 transition-transform group-hover:scale-105">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                </div>
                <p class="text-2xl font-semibold tabular-nums tracking-tight text-warning">{{ $discontinued }}</p>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="flex flex-col justify-between rounded-lg border border-border bg-card p-4 shadow-sm sm:min-h-[6.5rem]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-foreground/70">Mastery rate</p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums text-secondary">{{ $masteryDisplay }}%</p>
                    </div>
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-secondary/15 text-secondary ring-1 ring-secondary/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 13l3-3 3 2 4-5" />
                        </svg>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-center justify-between text-[11px] font-medium text-foreground/50">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                    <progress
                        class="mt-1 h-2 w-full overflow-hidden rounded-full [&::-webkit-progress-bar]:rounded-full [&::-webkit-progress-bar]:bg-muted [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-secondary [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-secondary"
                        max="100"
                        value="{{ $masteryRate }}"
                        aria-label="Mastery rate {{ $masteryDisplay }} percent">
                    </progress>
                </div>
            </div>

            <div class="flex flex-col justify-between rounded-lg border border-border bg-card p-4 shadow-sm sm:min-h-[6.5rem] {{ $ssaGap > 0 ? 'border-warning/40 bg-warning/5' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium text-foreground/70">SSAs w/o goals</p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums {{ $ssaGap > 0 ? 'text-warning' : 'text-foreground' }}">{{ $ssaGap }}</p>
                    </div>
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md {{ $ssaGap > 0 ? 'bg-warning/15 text-warning ring-1 ring-warning/25' : 'bg-muted text-foreground/60 ring-1 ring-border' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </span>
                </div>
                @if ($ssaGap > 0)
                    <p class="mt-2 text-xs text-foreground/60">Add or activate goals on those SSAs so session logging stays aligned.</p>
                @else
                    <p class="mt-2 text-xs text-foreground/60">Every SSA has at least one active goal.</p>
                @endif
            </div>
        </div>
    </div>
</x-ui::card>
