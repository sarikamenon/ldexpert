@props(['ssa', 'context' => 'therapist'])

<div class="space-y-6">
    {{-- Section A: Core Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Core Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">SSA ID</dt>
                <dd class="mt-1 text-sm text-foreground">#{{ $ssa->id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Status</dt>
                <dd class="mt-1 text-sm">
                    <x-ui::badge :variant="match ($ssa->status) {
                        \App\Enums\SSAStatus::ACTIVE => 'success',
                        \App\Enums\SSAStatus::PENDING => 'warning',
                        \App\Enums\SSAStatus::COMPLETED => 'primary',
                        \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                        default => 'secondary',
                    }">
                        {{ $ssa->status->label() }}
                    </x-ui::badge>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Student</dt>
                <dd class="mt-1 text-sm text-foreground">
                    @if ($ssa->student)
                        <a href="{{ $context === 'admin' ? route('admin.students.show', $ssa->student) : route('therapist.students.show', $ssa->student) }}"
                            class="text-primary hover:underline">
                            {{ $ssa->student->name }}
                        </a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">School/Family</dt>
                <dd class="mt-1 text-sm text-foreground">
                    @if ($context === 'admin' && $ssa->student?->studentProfile?->school)
                        <a href="{{ route('admin.schools.show', $ssa->student->studentProfile->school) }}"
                            class="text-primary hover:underline">
                            {{ $ssa->student->studentProfile->school->display_name }}
                        </a>
                    @else
                        {{ $ssa->student?->studentProfile?->school?->display_name ?? '—' }}
                    @endif
                </dd>
            </div>
            @if ($context === 'admin')
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Assigned Therapist</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        @if ($ssa->assignedTherapist)
                            <a href="{{ route('admin.therapists.show', $ssa->assignedTherapist) }}"
                                class="text-primary hover:underline">
                                {{ $ssa->assignedTherapist->name }}
                            </a>
                        @else
                            <span class="text-foreground/50">Unassigned</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </x-ui::card>

    {{-- Section B: Service Details --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Service Details</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Primary Service</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->primaryService->name ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section C: Schedule & Frequency --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Schedule & Frequency</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Start Date</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->start_date->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">End Date</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->end_date->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Duration</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->start_date->diffInDays($ssa->end_date) + 1 }} days
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Frequency</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->frequency->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Sessions per Frequency</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $ssa->sessions_per_frequency }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Minutes per Session</dt>
                <dd class="mt-1 text-sm text-foreground">{{ number_format($ssa->minutes_per_session) }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section D: Usage Metrics --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Usage Metrics</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">THO Hours</dt>
                <dd class="mt-1 text-sm text-foreground font-semibold">{{ number_format($ssa->tho_hours, 2) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Served Hours</dt>
                <dd class="mt-1 text-sm text-foreground font-semibold">{{ number_format($ssa->served_hours, 2) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Remaining Hours</dt>
                <dd class="mt-1 text-sm text-foreground font-semibold">
                    {{ number_format(max(0, $ssa->tho_hours - $ssa->served_hours), 2) }}
                </dd>
            </div>
        </dl>
    </x-ui::card>

    @if ($context === 'admin')
        {{-- Section E: Administrative --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Administrative Details</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
                @if ($ssa->calculated_minutes)
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Calculated Minutes</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ number_format($ssa->calculated_minutes) }}</dd>
                    </div>
                @endif
                @if ($ssa->adjusted_minutes)
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Adjusted Minutes</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ number_format($ssa->adjusted_minutes) }}</dd>
                    </div>
                @endif
                @if ($ssa->adjustment_notes)
                    <div class="md:col-span-2">
                        <dt class="text-sm font-medium text-foreground/70">Adjustment Notes</dt>
                        <dd class="mt-1 text-sm text-foreground whitespace-pre-line">{{ $ssa->adjustment_notes }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Created At</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ app(\App\Domain\Time\UserTimezoneService::class)->toUserTimezone($ssa->created_at)->format('M d, Y g:i A') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Last Updated</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ app(\App\Domain\Time\UserTimezoneService::class)->toUserTimezone($ssa->updated_at)->format('M d, Y g:i A') }}
                    </dd>
                </div>
            </dl>
        </x-ui::card>
    @endif

    @if ($ssa->additional_notes)
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Additional Notes</h3>
            <p class="text-sm text-foreground whitespace-pre-line">{{ $ssa->additional_notes }}</p>
        </x-ui::card>
    @endif
</div>
