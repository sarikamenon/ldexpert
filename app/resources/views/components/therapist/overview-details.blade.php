@props(['therapist', 'context' => 'admin'])

<div class="space-y-6">
    {{-- Section A: Basic Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Basic Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $therapist->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Date of Birth</dt>
                <dd class="mt-1 text-sm text-foreground">
                    {{ optional($therapist->therapistProfile?->dob)->format('M d, Y') ?? '—' }}
                </dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section B: Professional Details --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Professional Details</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Max Weekly Hours</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $therapist->therapistProfile?->max_weekly_hours ?? '—' }}
                </dd>
            </div>
            {{-- Add more professional fields if available in the future --}}
        </dl>
    </x-ui::card>

    {{-- Section C: Location & Contact --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Location & Contact</h3>
        <dl class="grid grid-cols-1 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Address</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $therapist->therapistProfile?->address ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section D: Additional Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Additional Information</h3>
        <dl class="grid grid-cols-1 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Notes</dt>
                <dd class="mt-1 text-sm text-foreground whitespace-pre-line">
                    {{ $therapist->therapistProfile?->comments ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>
</div>
