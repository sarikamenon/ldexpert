@props(['therapist', 'context' => 'admin'])

<div class="space-y-6">
    {{-- Section A: Employment & Identity --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Employment & Identity</h3>
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Title</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->title?->value ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">First Name</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->first_name ?? '—' }}</dd>
                </div>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Last Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->last_name ?? '—' }}</dd>
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: Contact & Account Details --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Contact & Account Details</h3>
        <div class="space-y-4">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Personal Email</dt>
                <dd class="mt-1 text-sm text-primary">
                    {{ $therapist->therapistProfile?->personal_email ?? $therapist->email }}</dd>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Phone</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">NOVA Email</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->ld_email ?? '—' }}</dd>
                </div>
            </div>

            <div>
                <dt class="text-sm font-medium text-foreground/70">LLC Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->llc_name ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-foreground/70">Address</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $therapist->therapistProfile?->address ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-foreground/70">Default Meeting Location</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $therapist->therapistProfile?->default_meeting_location ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-foreground/70">Internal Comments</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $therapist->therapistProfile?->comments ?? '—' }}</dd>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Max Weekly Hours</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ $therapist->therapistProfile?->max_weekly_hours ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Hourly Rate</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ $therapist->therapistProfile?->hourly_rate !== null ? '$' . number_format((float) $therapist->therapistProfile->hourly_rate, 2) : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Date of Birth</dt>
                    <dd class="mt-1 text-sm text-foreground">
                        {{ optional($therapist->therapistProfile?->dob)->format('M d, Y') ?? '—' }}
                    </dd>
                </div>
            </div>
        </div>
    </x-ui::card>
</div>
