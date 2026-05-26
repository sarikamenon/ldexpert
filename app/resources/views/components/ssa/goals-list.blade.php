@props([
    'ssa',
    'goals',
    'createUrl',
    'editUrlResolver',
    'statusUrlResolver',
    'canEdit' => false,
])

@php
    $activeCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::ACTIVE)->count();
    $masteredCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::MASTERED)->count();
    $discontinuedCount = $goals->filter(fn ($g) => $g->status === \App\Enums\SSAGoalStatus::DISCONTINUED)->count();
@endphp

<x-ui::card class="p-6 space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-foreground">Goals</h3>
            <p class="text-xs text-foreground/60 mt-1">Goals track what the student is working toward across sessions on this SSA.</p>
        </div>
        @if ($canEdit)
            <x-ui::button variant="primary" :href="$createUrl">
                + Add Goal
            </x-ui::button>
        @endif
    </div>

    @if ($goals->isEmpty())
        <x-ui::empty-state
            title="No goals yet"
            :description="$canEdit ? 'Add the first goal to start tracking progress on this SSA.' : 'Goals have not been added for this SSA yet.'"
            :action-label="$canEdit ? 'Add Goal' : null"
            :action-href="$canEdit ? $createUrl : null">
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
                <span class="w-2.5 h-2.5 rounded-full bg-primary inline-block"></span>
                <span class="font-medium text-foreground">{{ $activeCount }} Active</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-success inline-block"></span>
                <span class="font-medium text-foreground">{{ $masteredCount }} Mastered</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-border inline-block"></span>
                <span class="font-medium text-foreground">{{ $discontinuedCount }} Discontinued</span>
            </span>
        </div>

        <div class="space-y-4">
            @foreach ($goals as $goal)
                @php
                    $editUrl = $editUrlResolver($goal);
                    $statusUrl = $statusUrlResolver($goal);
                    $objectiveCollapseId = 'gl-obj-' . $goal->id;
                    $progressCollapseId = 'gl-prog-' . $goal->id;
                @endphp

                <div class="rounded-lg border border-border bg-white overflow-hidden">
                    {{-- Goal header --}}
                    <div class="flex items-center justify-between gap-3 px-4 pt-4 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="text-base font-bold text-foreground">Goal {{ $goal->number }}</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $goal->status->badgeClass() }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $goal->status->dotColor() }} inline-block"></span>
                                {{ $goal->status->label() }}
                            </span>
                        </div>

                        @if ($canEdit)
                            <div class="flex items-center gap-2">
                                <x-ui::button variant="secondary" size="sm" :href="$editUrl" class="gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    </svg>
                                    Edit
                                </x-ui::button>
                                @if ($goal->can_transition_status)
                                    <x-ui::button variant="success" size="sm"
                                        class="ssa-goal-status-btn gap-1.5"
                                        data-status-url="{{ $statusUrl }}"
                                        data-status="mastered"
                                        data-confirm-title="Mark goal as mastered?"
                                        data-confirm-text="Goal #{{ $goal->number }} will move to the Mastered list and stop appearing on session logs."
                                        data-confirm-button="Yes, mark mastered"
                                        data-confirm-icon="success"
                                        data-success-message="Goal marked as mastered.">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        Mark Mastered
                                    </x-ui::button>
                                    <x-ui::button variant="danger" size="sm"
                                        class="ssa-goal-status-btn gap-1.5"
                                        data-status-url="{{ $statusUrl }}"
                                        data-status="discontinued"
                                        data-confirm-title="Discontinue this goal?"
                                        data-confirm-text="Goal #{{ $goal->number }} will be discontinued and stop appearing on session logs."
                                        data-confirm-button="Yes, discontinue"
                                        data-confirm-icon="warning"
                                        data-success-message="Goal discontinued.">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Discontinue
                                    </x-ui::button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="px-4 pb-4 space-y-3">
                        {{-- Goal text (required, always shown) --}}
                        <div>
                            <p class="text-xs font-semibold tracking-widest uppercase text-foreground/50 mb-1.5">Goal</p>
                            <blockquote class="border-l-4 border-l-border bg-muted/30 rounded-r pl-3 pr-3 py-2">
                                <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->goal }}</p>
                            </blockquote>
                        </div>

                        {{-- Objectives (optional, collapsible) --}}
                        <div>
                            <button type="button"
                                class="gl-objectives-toggle inline-flex items-center gap-1.5 text-xs font-medium text-foreground/60 hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                                aria-expanded="false"
                                aria-controls="{{ $objectiveCollapseId }}">
                                <svg class="gl-obj-chevron w-3.5 h-3.5 transition-transform duration-150" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                                <span>Show objectives</span>
                            </button>
                            <div id="{{ $objectiveCollapseId }}" class="gl-objectives-panel hidden mt-2">
                                <blockquote class="border-l-4 border-l-border bg-muted/30 rounded-r pl-3 pr-3 py-2">
                                    @if ($goal->objective)
                                        <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->objective }}</p>
                                    @else
                                        <p class="text-sm text-foreground/40 italic">No objectives recorded.</p>
                                    @endif
                                </blockquote>
                            </div>
                        </div>

                        {{-- Progress Notes (collapsible) --}}
                        <div>
                            <button type="button"
                                class="gl-progress-toggle inline-flex items-center gap-1.5 text-xs font-medium text-foreground/60 hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                                aria-expanded="false"
                                aria-controls="{{ $progressCollapseId }}">
                                <svg class="gl-prog-chevron w-3.5 h-3.5 transition-transform duration-150" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
                                </svg>
                                <span>Show progress notes</span>
                            </button>
                            <div id="{{ $progressCollapseId }}" class="gl-progress-panel hidden mt-2">
                                <blockquote class="border-l-4 border-l-border bg-muted/30 rounded-r pl-3 pr-3 py-2">
                                    @if ($goal->progress)
                                        <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->progress }}</p>
                                    @else
                                        <p class="text-sm text-foreground/40 italic">No progress recorded yet.</p>
                                    @endif
                                </blockquote>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui::card>
