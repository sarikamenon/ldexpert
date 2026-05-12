@props([
    'ssa',
    'goalMetrics' => [],
    'context' => 'therapist',
])

@php
    $isAdmin = $context === 'admin';
    $goalsTabUrl = $isAdmin
        ? route('admin.ssas.show', ['ssa' => $ssa, 'tab' => 'goals'])
        : route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']);
@endphp

<div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Quick Info Card --}}
    <x-ui::card class="p-6 h-full">
        <h3 class="text-lg font-semibold text-foreground mb-4">Quick Info</h3>
        <div class="space-y-4">
            {{-- Student --}}
            <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-foreground/60">Student</p>
                    <div class="flex items-baseline gap-2">
                        <a href="{{ $isAdmin ? route('admin.students.show', $ssa->student) : route('therapist.students.show', $ssa->student) }}"
                            class="text-sm font-semibold text-primary hover:underline truncate">
                            {{ $ssa->student->name }}
                        </a>
                        @if($ssa->student->studentProfile?->grade_level)
                            <span class="text-xs text-foreground/50">({{ $ssa->student->studentProfile->grade_level }})</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Parent Contact --}}
            <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-foreground/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-foreground/60">Parent/Guardian</p>
                    <p class="text-sm font-medium text-foreground truncate">
                        {{ $ssa->student->studentProfile?->parent_guardian_name ?? '—' }}
                    </p>
                    @if($ssa->student->studentProfile?->parent_guardian_phone || $ssa->student->studentProfile?->parent_guardian_email)
                        <p class="text-xs text-foreground/50 truncate">
                            {{ $ssa->student->studentProfile?->parent_guardian_phone ?? $ssa->student->studentProfile?->parent_guardian_email }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- School --}}
            <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted text-foreground/60">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-foreground/60">School/Family</p>
                    @if ($ssa->student->studentProfile?->school)
                        @if ($isAdmin)
                            <a href="{{ route('admin.schools.show', $ssa->student->studentProfile->school) }}"
                                class="text-sm font-medium text-primary hover:underline truncate block">
                                {{ $ssa->student->studentProfile->school->display_name }}
                            </a>
                        @else
                            <p class="text-sm font-medium text-foreground truncate">
                                {{ $ssa->student->studentProfile->school->display_name }}
                            </p>
                        @endif
                    @else
                        <p class="text-sm font-medium text-foreground/50">—</p>
                    @endif
                </div>
            </div>

            {{-- Therapist (Admin only) --}}
            @if ($isAdmin)
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md {{ $ssa->assignedTherapist ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-foreground/60">Assigned Therapist</p>
                        @if ($ssa->assignedTherapist)
                            <a href="{{ route('admin.therapists.show', $ssa->assignedTherapist) }}"
                                class="text-sm font-medium text-primary hover:underline truncate block">
                                {{ $ssa->assignedTherapist->name }}
                            </a>
                        @else
                            <p class="text-sm font-medium text-warning">Unassigned</p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Service --}}
            <div class="flex items-start gap-3 pt-2 border-t border-border">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-secondary/10 text-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-foreground/60">Service</p>
                    <p class="text-sm font-medium text-foreground truncate">{{ $ssa->primaryService->name ?? '—' }}</p>
                    <p class="text-xs text-foreground/50">
                        {{ $ssa->minutes_per_session }}min × {{ $ssa->sessions_per_frequency }}/{{ strtolower($ssa->frequency->label()) }}
                    </p>
                </div>
            </div>
        </div>
    </x-ui::card>

    {{-- Goals Snapshot --}}
    <x-ssa.goals-snapshot :goal-metrics="$goalMetrics" :goals-tab-url="$goalsTabUrl" />
</div>
