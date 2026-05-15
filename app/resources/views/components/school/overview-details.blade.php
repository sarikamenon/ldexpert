@props(['school', 'context' => 'admin'])

<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Section A: School/Family Information --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">School/Family Information</h3>
            <dl class="space-y-4">
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Full School/Family Name</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $school->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">NOVA School/Family Name</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $school->display_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Address</dt>
                    <dd class="mt-1 text-sm text-foreground whitespace-pre-line">{{ $school->address ?? '—' }}</dd>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">State</dt>
                        <dd class="mt-1 text-sm text-foreground">
                            {{ $school->state ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Time Zone</dt>
                        <dd class="mt-1 text-sm text-foreground">
                            {{ $school->timezone ? \App\Constants\UsTimezones::getTimezoneLabel($school->timezone) : '—' }}
                        </dd>
                    </div>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Manager</dt>
                    <dd class="mt-1 text-sm text-foreground">{{ $school->manager?->name ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui::card>

        {{-- Section B: Contact Information --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Contact Information</h3>
            <dl class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Primary Contact First Name</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ $school->contact_first_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Primary Contact Last Name</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ $school->contact_last_name ?? '—' }}</dd>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Phone Number</dt>
                        <dd class="mt-1 text-sm text-foreground">{{ $school->contact_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-foreground/70">Email Address</dt>
                        <dd class="mt-1 text-sm text-primary">{{ $school->contact_email ?? '—' }}</dd>
                    </div>
                </div>
                <div>
                    <dt class="text-sm font-medium text-foreground/70">Invoice Email</dt>
                    <dd class="mt-1 text-sm text-primary">{{ $school->invoice_email ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui::card>
    </div>

    {{-- Section C: School/Family Characteristics --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">School/Family Characteristics</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-4 gap-y-6">
            <div>
                <dt class="text-sm font-medium text-foreground/70">School/Family Type</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->school_type ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-foreground/70">Is Private Student?</dt>
                <dd class="mt-1 text-sm text-foreground">
                    <x-ui::badge :variant="$school->is_private_student ? 'primary' : 'secondary'">
                        {{ $school->is_private_student ? 'Yes' : 'No' }}
                    </x-ui::badge>
                </dd>
            </div>
            @if($school->is_private_student)
            <div>
                <dt class="text-sm font-medium text-foreground/70">Auto-Extend Contract & SSAs?</dt>
                <dd class="mt-1 text-sm text-foreground">
                    <x-ui::badge :variant="$school->is_auto_extend ? 'primary' : 'secondary'">
                        {{ $school->is_auto_extend ? 'Yes' : 'No' }}
                    </x-ui::badge>
                </dd>
            </div>
            @endif
            <div>
                <dt class="text-sm font-medium text-foreground/70">Past session submission</dt>
                <dd class="mt-1 text-sm text-foreground">
                    <x-ui::badge :variant="$school->non_billable_scheduling ? 'primary' : 'secondary'">
                        {{ $school->non_billable_scheduling ? 'Excluded from Past Sessions queue' : 'Not excluded' }}
                    </x-ui::badge>
                </dd>
            </div>
            <div class="md:col-span-3">
                <dt class="text-sm font-medium text-foreground/70">External EMR School/Family Name</dt>
                <dd class="mt-1 text-sm text-foreground">{{ $school->external_emr_name ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>
</div>
