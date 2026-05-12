@php
    $activeCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::ACTIVE)->count();
    $masteredCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::MASTERED)->count();
    $discontinuedCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::DISCONTINUED)->count();

    $goalsBySsa = $goals->groupBy(fn ($g) => $g->ssa_id);
    $ssaRoute = $ssaRoute ?? 'therapist.ssas.show';
@endphp

<div class="space-y-4">
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
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
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

    @if ($goals->isEmpty())
        <x-ui::card class="p-6">
            <x-ui::empty-state
                title="No goals yet"
                description="Goals will appear here once they are added to one of the student's SSAs.">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-12 h-12">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </x-ui::empty-state>
        </x-ui::card>
    @else
        {{-- SSA sections --}}
        <div class="space-y-4" id="goals-ssa-list">
            @foreach ($goalsBySsa as $ssaId => $ssaGoals)
                @php
                    $ssa = $ssaGoals->first()->ssa;
                    $ssaActiveCount = $ssaGoals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::ACTIVE)->count();
                    $ssaMasteredCount = $ssaGoals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::MASTERED)->count();
                    $ssaDiscontinuedCount = $ssaGoals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::DISCONTINUED)->count();
                    $ssaSectionId = 'ssa-section-' . $ssaId;
                    $ssaBodyId = 'ssa-body-' . $ssaId;
                @endphp

                <div class="ssa-goal-section rounded-xl border border-border bg-white overflow-hidden"
                     data-ssa-id="{{ $ssaId }}"
                     data-active="{{ $ssaActiveCount }}"
                     data-mastered="{{ $ssaMasteredCount }}"
                     data-discontinued="{{ $ssaDiscontinuedCount }}">

                    {{-- SSA header row --}}
                    <button type="button"
                        class="ssa-toggle w-full flex items-center justify-between gap-4 px-5 py-4 bg-muted/30 hover:bg-muted/50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
                        aria-expanded="true"
                        aria-controls="{{ $ssaBodyId }}">

                        <div class="flex items-center gap-3 min-w-0">
                            <a href="{{ route($ssaRoute, $ssa) }}"
                                class="text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                                onclick="event.stopPropagation()">
                                SSA #{{ $ssa->id }}
                            </a>
                            @if ($ssa->primaryService?->name)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-muted text-foreground/60 border border-border uppercase tracking-wide">
                                    {{ $ssa->primaryService->name }}
                                </span>
                            @endif
                            <span class="text-xs text-foreground/50">{{ $ssaGoals->count() }} {{ Str::plural('goal', $ssaGoals->count()) }}</span>
                        </div>

                        <div class="flex items-center gap-3 flex-shrink-0">
                            @if ($ssaActiveCount > 0)
                                <span class="text-xs font-medium text-primary">{{ $ssaActiveCount }} Active</span>
                            @endif
                            @if ($ssaMasteredCount > 0)
                                <span class="text-xs font-medium text-emerald-600">{{ $ssaMasteredCount }} Mastered</span>
                            @endif
                            @if ($ssaDiscontinuedCount > 0)
                                <span class="text-xs font-medium text-foreground/40">{{ $ssaDiscontinuedCount }} Discontinued</span>
                            @endif
                            <svg class="ssa-chevron w-4 h-4 text-foreground/40 transition-transform duration-200 rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                            </svg>
                        </div>
                    </button>

                    {{-- Goals list --}}
                    <div id="{{ $ssaBodyId }}" class="ssa-goals-body">
                        @foreach ($ssaGoals as $index => $goal)
                            @php
                                $isActive = $goal->status === \App\Enums\SSAGoalStatus::ACTIVE;
                                $isMastered = $goal->status === \App\Enums\SSAGoalStatus::MASTERED;
                                $isDiscontinued = $goal->status === \App\Enums\SSAGoalStatus::DISCONTINUED;

                                $leftBorderColor = $isActive ? '#5563b8' : ($isMastered ? '#10b981' : '#e5e7eb');
                                $badgeBg = $isActive
                                    ? 'bg-primary/10 text-primary'
                                    : ($isMastered ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-foreground/50');
                                $dotColor = $isActive ? 'bg-primary' : ($isMastered ? 'bg-emerald-500' : 'bg-foreground/30');
                                $statusSlug = $isActive ? 'active' : ($isMastered ? 'mastered' : 'discontinued');
                                $progressNotesId = 'progress-notes-' . $ssaId . '-' . $goal->id;
                            @endphp

                            <div class="goal-item {{ $index > 0 ? 'border-t border-border' : '' }}"
                                 style="border-left: 4px solid {{ $leftBorderColor }};"
                                 data-status="{{ $statusSlug }}">
                                <div class="px-5 pt-4 pb-1">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-sm font-bold text-foreground">Goal {{ $index + 1 }}</span>
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeBg }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} inline-block"></span>
                                            {{ $goal->status->label() }}
                                        </span>
                                    </div>

                                    <p class="text-xs font-semibold tracking-widest uppercase text-foreground/40 mt-3 mb-1">Objective</p>
                                    <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->objective }}</p>
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
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
