@php
    $ssaRoute = $ssaRoute ?? 'therapist.ssas.show';
    $goalsRouteGroup = $goalsRouteGroup ?? 'therapist.ssas.goals';

    /** @var \Illuminate\Support\Collection<int, \App\Models\ServiceSupportAgreement> $tabSsas */
    $tabSsas = isset($studentSsasForGoalsTab) ? $studentSsasForGoalsTab : collect();
    if ($tabSsas->isEmpty() && $goals->isNotEmpty()) {
        $tabSsas = $goals->pluck('ssa')->unique('id')->filter()->sortByDesc('id')->values();
    }
@endphp

<x-ui::card class="p-6 space-y-4">
    {{-- Page header with filter pills --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-foreground">Goals</h3>
            <p class="text-xs text-foreground/60 mt-0.5">All goals across every SSA for this student.</p>
        </div>

        @if ($goals->isNotEmpty())
            <div class="flex items-center gap-2 flex-shrink-0" id="goals-filter-pills">
                <button type="button"
                    data-filter="all"
                    class="goals-filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition-colors
                           border-foreground bg-transparent text-foreground ring-1 ring-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-pressed="true">
                    All goals
                </button>
                @if ($activeCount > 0)
                    <button type="button"
                        data-filter="active"
                        class="goals-filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-border bg-transparent text-foreground/70 hover:border-foreground/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-pressed="false">
                        <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                        {{ $activeCount }} Active
                    </button>
                @endif
                @if ($masteredCount > 0)
                    <button type="button"
                        data-filter="mastered"
                        class="goals-filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-border bg-transparent text-foreground/70 hover:border-foreground/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-pressed="false">
                        <span class="w-2 h-2 rounded-full bg-success inline-block"></span>
                        {{ $masteredCount }} Mastered
                    </button>
                @endif
                @if ($discontinuedCount > 0)
                    <button type="button"
                        data-filter="discontinued"
                        class="goals-filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-border bg-transparent text-foreground/70 hover:border-foreground/40 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-pressed="false">
                        <span class="w-2 h-2 rounded-full bg-foreground/30 inline-block"></span>
                        {{ $discontinuedCount }} Discontinued
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if ($tabSsas->isEmpty() && $goals->isEmpty())
        <x-ui::empty-state
            title="No goals yet"
            description="Goals will appear here once they are added to one of the student's SSAs.">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-12 h-12">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </x-ui::empty-state>
    @else
        {{-- SSA sections --}}
        <div class="space-y-4" id="goals-ssa-list">
            @foreach ($tabSsas as $ssa)
                @php
                    $ssaGoals = $goals->where('ssa_id', $ssa->id)->values();
                    $ssaActiveCount = $ssaGoals->filter(fn ($g) => $g->status->isActive())->count();
                    $ssaMasteredCount = $ssaGoals->filter(fn ($g) => $g->status->isMastered())->count();
                    $ssaDiscontinuedCount = $ssaGoals->filter(fn ($g) => $g->status->isDiscontinued())->count();
                    $ssaBodyId = 'ssa-body-' . $ssa->id;
                @endphp

                <div class="ssa-goal-section rounded-xl border border-border bg-card overflow-hidden"
                     data-ssa-id="{{ $ssa->id }}"
                     data-active="{{ $ssaActiveCount }}"
                     data-mastered="{{ $ssaMasteredCount }}"
                     data-discontinued="{{ $ssaDiscontinuedCount }}">

                    {{-- SSA header: SSA link + stats + chevron toggle; Add Goal stays outside toggle --}}
                    <div class="flex flex-col border-b border-border bg-muted/30 sm:flex-row sm:items-stretch">
                        <div class="flex min-w-0 flex-1 items-center justify-between gap-4 px-5 py-4">
                            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                                <a href="{{ route($ssaRoute, $ssa) }}"
                                    class="rounded text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    SSA #{{ $ssa->id }}
                                </a>
                                @if ($ssa->primaryService?->name)
                                    <span class="inline-flex items-center rounded border border-border bg-muted px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-foreground/60">
                                        {{ $ssa->primaryService->name }}
                                    </span>
                                @endif
                                <span class="text-xs text-foreground/50">{{ $ssaGoals->count() }} {{ Str::plural('goal', $ssaGoals->count()) }}</span>
                            </div>

                            <div class="flex flex-shrink-0 items-center gap-2 sm:gap-3">
                                @if ($ssaActiveCount > 0)
                                    <span class="text-xs font-medium text-primary">{{ $ssaActiveCount }} Active</span>
                                @endif
                                @if ($ssaMasteredCount > 0)
                                    <span class="text-xs font-medium text-success">{{ $ssaMasteredCount }} Mastered</span>
                                @endif
                                @if ($ssaDiscontinuedCount > 0)
                                    <span class="text-xs font-medium text-foreground/40">{{ $ssaDiscontinuedCount }} Discontinued</span>
                                @endif
                                <button type="button"
                                    class="ssa-toggle rounded p-1 text-foreground/40 transition-colors hover:bg-muted/80 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-expanded="true"
                                    aria-controls="{{ $ssaBodyId }}"
                                    aria-label="Show or hide goals for SSA #{{ $ssa->id }}">
                                    <svg class="ssa-chevron h-4 w-4 rotate-180 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @can('create', [\App\Models\SSAGoal::class, $ssa])
                            <div class="flex items-center justify-center border-t border-border px-5 py-3 sm:border-l sm:border-t-0 sm:px-4">
                                <x-ui::button variant="primary" size="sm" class="w-full justify-center sm:w-auto"
                                    href="{{ route($goalsRouteGroup . '.create', $ssa) }}{{ ($goalCreateReturnTo ?? '') !== '' ? '?return_to=' . urlencode($goalCreateReturnTo) : '' }}">
                                    + Add Goal
                                </x-ui::button>
                            </div>
                        @endcan
                    </div>

                    {{-- Goals list --}}
                    <div id="{{ $ssaBodyId }}" class="ssa-goals-body">
                        @forelse ($ssaGoals as $index => $goal)
                            @php
                                $progressNotesId = 'progress-notes-' . $ssa->id . '-' . $goal->id;
                                $objectivePanelId = 'objective-' . $ssa->id . '-' . $goal->id;
                            @endphp

                            <div class="goal-item {{ $index > 0 ? 'border-t border-border' : '' }} {{ $goal->status->borderClass() }}"
                                 data-status="{{ $goal->status->slug() }}">
                                <div class="px-5 pt-4 pb-1">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex items-center gap-2.5 flex-wrap min-w-0">
                                            <span class="text-sm font-bold text-foreground">Goal {{ $goal->number }}</span>
                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $goal->status->badgeClass() }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $goal->status->dotColor() }} inline-block"></span>
                                                {{ $goal->status->label() }}
                                            </span>
                                        </div>

                                        <div class="flex flex-shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                                            @can('update', $goal)
                                                <x-ui::button variant="secondary" size="sm"
                                                    :href="route($goalsRouteGroup . '.edit', ['ssa' => $ssa, 'goal' => $goal])"
                                                    class="gap-1.5">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                    </svg>
                                                    Edit
                                                </x-ui::button>
                                            @endcan
                                            @can('changeStatus', $goal)
                                                @if ($goal->can_transition_status)
                                                    <x-ui::button variant="success" size="sm" type="button"
                                                        class="ssa-goal-status-btn gap-1.5"
                                                        data-status-url="{{ route($goalsRouteGroup . '.change-status', ['ssa' => $ssa, 'goal' => $goal]) }}"
                                                        data-status="mastered"
                                                        data-confirm-title="Mark goal as mastered?"
                                                        data-confirm-text="Goal #{{ $goal->number }} will move to the Mastered list and stop appearing on session logs."
                                                        data-confirm-button="Yes, mark mastered"
                                                        data-confirm-icon="success"
                                                        data-success-message="Goal marked as mastered.">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                        </svg>
                                                        Mark Mastered
                                                    </x-ui::button>
                                                    <x-ui::button variant="danger" size="sm" type="button"
                                                        class="ssa-goal-status-btn gap-1.5"
                                                        data-status-url="{{ route($goalsRouteGroup . '.change-status', ['ssa' => $ssa, 'goal' => $goal]) }}"
                                                        data-status="discontinued"
                                                        data-confirm-title="Discontinue this goal?"
                                                        data-confirm-text="Goal #{{ $goal->number }} will be discontinued and stop appearing on session logs."
                                                        data-confirm-button="Yes, discontinue"
                                                        data-confirm-icon="warning"
                                                        data-success-message="Goal discontinued.">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                        Discontinue
                                                    </x-ui::button>
                                                @endif
                                            @endcan
                                        </div>
                                    </div>

                                    {{-- Goal (required, always shown) --}}
                                    <p class="text-xs font-semibold tracking-widest uppercase text-foreground/40 mt-3 mb-1">Goal</p>
                                    <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->goal }}</p>
                                </div>

                                {{-- Objectives toggle (collapsible, optional) --}}
                                <div class="px-5 pt-1 {{ $goal->progress ? '' : 'pb-2' }}">
                                    <button type="button"
                                        class="objectives-toggle inline-flex items-center gap-1.5 text-xs font-medium text-foreground/60 hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                                        aria-expanded="false"
                                        aria-controls="{{ $objectivePanelId }}">
                                        <svg class="objectives-chevron w-3.5 h-3.5 transition-transform duration-150" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                        </svg>
                                        <span class="toggle-label">Show objectives</span>
                                    </button>

                                    <div id="{{ $objectivePanelId }}" class="objectives-panel hidden mt-3">
                                        @if ($goal->objective)
                                            <p class="text-sm text-foreground/70 whitespace-pre-wrap">{{ $goal->objective }}</p>
                                        @else
                                            <p class="text-sm text-foreground/40 italic">No objectives recorded.</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Progress notes toggle --}}
                                <div class="px-5 pb-4 pt-2">
                                    <button type="button"
                                        class="progress-notes-toggle inline-flex items-center gap-1.5 text-xs font-medium text-foreground/60 hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                                        aria-expanded="false"
                                        aria-controls="{{ $progressNotesId }}">
                                        <svg class="progress-chevron w-3.5 h-3.5 transition-transform duration-150" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                        </svg>
                                        <span class="toggle-label">Show progress notes</span>
                                    </button>

                                    <div id="{{ $progressNotesId }}" class="progress-notes-panel hidden mt-3">
                                        @if ($goal->progress)
                                            <p class="text-sm text-foreground/70 whitespace-pre-wrap italic">{{ $goal->progress }}</p>
                                        @else
                                            <p class="text-sm text-foreground/40 italic">No progress notes recorded yet.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-6 text-center border-t border-border">
                                <p class="text-sm text-foreground/60">No goals for this SSA yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui::card>
