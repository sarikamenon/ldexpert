@props(['school', 'context' => 'admin'])

<div class="space-y-6">
    {{-- Section A: Basic Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Basic Information</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Full Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->full_name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Display Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->display_name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">School Type</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->school_type ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">External EMR Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->external_emr_name ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section B: Location & Contact --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Location & Contact</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">State</dt>
                <dd class="mt-1 text-sm text-foreground">
                    {{ $school->state ? \App\Constants\UsStates::getStateName($school->state) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Timezone</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->timezone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Invoice Email</dt>
                <dd class="mt-1 text-sm text-primary">{{ $school->invoice_email ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    {{-- Section C: Configuration --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Configuration</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">Private Student?</dt>
                <dd class="mt-1 text-sm text-foreground">
                    <x-ui::badge :variant="$school->is_private_student ? 'primary' : 'secondary'">
                        {{ $school->is_private_student ? 'Yes' : 'No' }}
                    </x-ui::badge>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Non-billable Scheduling?</dt>
                <dd class="mt-1 text-sm text-foreground">
                    <x-ui::badge :variant="$school->non_billable_scheduling ? 'primary' : 'secondary'">
                        {{ $school->non_billable_scheduling ? 'Yes' : 'No' }}
                    </x-ui::badge>
                </dd>
            </div>
        </dl>
    </x-ui::card>
</div>
