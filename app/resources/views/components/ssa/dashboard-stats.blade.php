@props(['ssa'])

<div class="lg:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
    {{-- Student Info --}}
    <x-ui::card class="p-6 h-full">
        <h3 class="text-lg font-semibold text-foreground mb-4">Student Info</h3>
        <div class="space-y-4">
            <div>
                <p class="text-sm text-foreground/70">Student Name</p>
                <div class="flex items-baseline gap-2">
                    <a href="{{ request()->routeIs('admin.*') ? route('admin.students.show', $ssa->student) : route('therapist.students.show', $ssa->student) }}"
                        class="text-base font-semibold text-primary hover:underline">
                        {{ $ssa->student->name }}
                    </a>
                    @if($ssa->student->studentProfile?->grade_level)
                        <span class="text-sm text-foreground/60">({{ $ssa->student->studentProfile->grade_level }})</span>
                    @endif
                </div>
            </div>

            <div>
                <p class="text-sm text-foreground/70">Student Email</p>
                <p class="text-base font-medium text-foreground">
                    {{ $ssa->student->email ?? '—' }}
                </p>
            </div>

            <div>
                <p class="text-sm text-foreground/70">Parent Name</p>
                <p class="text-base font-medium text-foreground">
                    {{ $ssa->student->studentProfile?->parent_guardian_name ?? '—' }}
                </p>
            </div>

            <div>
                <p class="text-sm text-foreground/70">Parent Contact</p>
                <p class="text-base font-medium text-foreground">
                    {{ $ssa->student->studentProfile?->parent_guardian_phone ?? ($ssa->student->studentProfile?->parent_guardian_email ?? '—') }}
                </p>
            </div>

            <div>
                <p class="text-sm text-foreground/70">School</p>
                @if ($ssa->student->studentProfile?->school)
                    <a href="{{ request()->routeIs('admin.*') ? route('admin.schools.show', $ssa->student->studentProfile->school) : '#' }}"
                        class="text-base font-medium {{ request()->routeIs('admin.*') ? 'text-primary hover:underline' : 'text-foreground' }}">
                        {{ $ssa->student->studentProfile->school->display_name }}
                    </a>
                @else
                    <p class="text-base font-medium text-foreground">—</p>
                @endif
            </div>
        </div>
    </x-ui::card>

    {{-- SSA Info --}}
    <x-ui::card class="p-6 h-full">
        <h3 class="text-lg font-semibold text-foreground mb-4">SSA Info</h3>
        <div class="space-y-4">
            {{-- Primary Service --}}
            <div>
                <p class="text-sm text-foreground/70">Primary Service</p>
                <p class="text-base font-medium text-foreground">
                    {{ $ssa->primaryService->name ?? '—' }}
                </p>
            </div>

            {{-- Additional Services --}}
            <div>
                <p class="text-sm text-foreground/70">Additional Services</p>
                <div class="space-y-1">
                    @if ($ssa->additionalServices->isNotEmpty())
                        @foreach ($ssa->additionalServices as $service)
                            <div class="text-base font-medium text-foreground">{{ $service->name }}</div>
                        @endforeach
                    @else
                        <div class="text-base font-medium text-foreground/50">No Additional Services</div>
                    @endif
                </div>
            </div>

            <div class="pt-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-foreground/70 w-24">Minutes:</span>
                    <span class="text-base font-medium text-foreground">
                        {{ $ssa->minutes_per_session }} x {{ $ssa->sessions_per_frequency }}
                    </span>
                </div>
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-foreground/70 w-24">Frequency:</span>
                    <span class="text-base font-medium text-foreground">
                        {{ $ssa->frequency->label() }}
                    </span>
                </div>
            </div>

            {{-- SSA Duration --}}
            <div class="pt-2">
                <p class="text-sm text-foreground/70">SSA Duration</p>
                <p class="text-base font-medium text-foreground">
                    {{ $ssa->start_date->format('M d, Y') }} To {{ $ssa->end_date->format('M d, Y') }}
                </p>
            </div>
        </div>
    </x-ui::card>
</div>
