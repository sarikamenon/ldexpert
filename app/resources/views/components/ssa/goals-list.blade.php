@props([
    'ssa',
    'goals',
    'createUrl',
    'editUrlResolver',
    'statusUrlResolver',
    'canEdit' => false,
])

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
        <div class="space-y-4">
            @foreach ($goals as $goal)
                @php
                    $editUrl = $editUrlResolver($goal);
                    $statusUrl = $statusUrlResolver($goal);
                @endphp
                <div class="rounded-lg border border-border bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-foreground">#{{ $goal->number }}</span>
                            <x-ui::badge :variant="$goal->status->badgeVariant()">
                                {{ $goal->status->label() }}
                            </x-ui::badge>
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
                                        Mark Discontinued
                                    </x-ui::button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="mt-3 space-y-3">
                        <div>
                            <p class="text-xs font-medium text-foreground/70">Objective</p>
                            <p class="mt-1 text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->objective }}</p>
                        </div>

                        <div>
                            <p class="text-xs font-medium text-foreground/70">Progress</p>
                            @if ($goal->progress)
                                <p class="mt-1 text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->progress }}</p>
                            @else
                                <p class="mt-1 text-sm text-foreground/40 italic">No progress recorded yet.</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-ui::card>
