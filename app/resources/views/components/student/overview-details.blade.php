@props(['student', 'context' => 'therapist'])

<div class="space-y-6">
    {{-- Section A: Basic Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Basic Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">First Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->first_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Middle Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->middle_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Last Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->last_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Email</dt>
                <dd class="mt-1 text-sm text-primary">{{ $student->email }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Gender</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->gender ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Date of Birth</dt>
                <dd class="mt-1 text-sm text-foreground">
                    {{ optional($student->studentProfile?->date_of_birth)->format('M d, Y') ?? '—' }}
                </dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section B: School & Academic Info --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">School & Academic Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">School</dt>
                <dd class="mt-1 text-sm text-foreground">
                    @if ($context === 'admin' && $student->studentProfile?->school)
                        <a href="{{ route('admin.schools.show', $student->studentProfile->school) }}"
                            class="text-primary hover:underline">
                            {{ $student->studentProfile->school->display_name }}
                        </a>
                    @else
                        {{ $student->studentProfile->school->display_name ?? '—' }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Student ID</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->id_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Timezone</dt>
                <dd class="mt-1 text-sm text-foreground">
                    {{ $student->studentProfile?->timezone ? \App\Constants\UsTimezones::getTimezoneLabel($student->studentProfile->timezone) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Grade Level</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->grade_level ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section C: Parent / Guardian Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Parent / Guardian Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->parent_guardian_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Email</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->parent_guardian_email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Schedule Email</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->schedule_email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Phone</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->parent_guardian_phone ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section D: Address Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Address Information</h3>
        <dl class="grid grid-cols-1 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Address</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $student->studentProfile?->address ?? '—' }}</dd>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm font-medium text-foreground/70">City</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->city ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">State</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ $student->studentProfile?->state ? \App\Constants\UsStates::getStateName($student->studentProfile->state) : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">ZIP Code</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $student->studentProfile?->zip_code ?? '—' }}</dd>
                </div>
            </div>
        </dl>
    </x-ui::card>
</div>
