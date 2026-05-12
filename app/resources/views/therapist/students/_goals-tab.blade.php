@php
    $activeCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::ACTIVE)->count();
    $masteredCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::MASTERED)->count();
    $discontinuedCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::DISCONTINUED)->count();

    $goalsBySsa = $goals->groupBy(fn ($g) => $g->ssa_id);
    $ssaRoute = $ssaRoute ?? 'therapist.ssas.show';
@endphp

<x-ui::card class="p-6 space-y-6">
    <div>
        <h3 class="text-lg font-semibold text-foreground">Goals</h3>
        <p class="text-xs text-foreground/60 mt-1">All goals across every SSA for this student.</p>
    </div>

    @if ($goals->isEmpty())
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
        {{-- Status summary bar --}}
        <div class="flex items-center gap-5 px-4 py-3 rounded-lg border border-border bg-muted/40 text-sm">
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-violet-500 inline-block"></span>
                <span class="font-medium text-foreground">{{ $activeCount }} Active</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
                <span class="font-medium text-foreground">{{ $masteredCount }} Mastered</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-border inline-block"></span>
                <span class="font-medium text-foreground">{{ $discontinuedCount }} Discontinued</span>
            </span>
        </div>

        {{-- Goals grouped by SSA --}}
        <div class="space-y-8">
            @foreach ($goalsBySsa as $ssaId => $ssaGoals)
                @php $ssa = $ssaGoals->first()->ssa; @endphp
                <div class="space-y-3">
                    {{-- SSA heading --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ route($ssaRoute, $ssa) }}"
                            class="text-sm font-semibold text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded">
                            SSA #{{ $ssa->id }}
                            @if ($ssa->primaryService?->name)
                                · {{ $ssa->primaryService->name }}
                            @endif
                        </a>
                        <span class="text-xs text-foreground/50">{{ $ssaGoals->count() }} {{ Str::plural('goal', $ssaGoals->count()) }}</span>
                    </div>

                    <div class="space-y-3 pl-3 border-l-2 border-border">
                        @foreach ($ssaGoals as $index => $goal)
                            @php
                                $isActive = $goal->status === \App\Enums\SSAGoalStatus::ACTIVE;
                                $isMastered = $goal->status === \App\Enums\SSAGoalStatus::MASTERED;
                                $badgeBg = $isActive ? 'bg-violet-100 text-violet-700' : ($isMastered ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-foreground/50');
                                $dotColor = $isActive ? 'bg-violet-500' : ($isMastered ? 'bg-emerald-500' : 'bg-foreground/30');
                            @endphp

                            <div class="rounded-lg border border-border bg-white overflow-hidden">
                                <div class="flex items-center gap-2.5 px-4 pt-4 pb-2">
                                    <span class="text-sm font-bold text-foreground">Goal {{ $index + 1 }}</span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeBg }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }} inline-block"></span>
                                        {{ $goal->status->label() }}
                                    </span>
                                </div>

                                <div class="px-4 pb-4 space-y-3">
                                    <div>
                                        <p class="text-xs font-semibold tracking-widest uppercase text-foreground/50 mb-1.5">Objective</p>
                                        <blockquote class="border-l-4 border-l-border bg-muted/30 rounded-r pl-3 pr-3 py-2">
                                            <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->objective }}</p>
                                        </blockquote>
                                    </div>

                                    @if ($goal->progress)
                                        <div>
                                            <p class="text-xs font-semibold tracking-widest uppercase text-foreground/50 mb-1.5">Progress Notes</p>
                                            <blockquote class="border-l-4 border-l-border bg-muted/30 rounded-r pl-3 pr-3 py-2">
                                                <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->progress }}</p>
                                            </blockquote>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui::card>
