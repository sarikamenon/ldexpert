@php
    $isEdit = isset($school) && $school->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.schools.update', $school) : route('admin.schools.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PATCH')
    @endif

    {{-- Section A: School/Family Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">School/Family Information</h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="full_name" value="Full School/Family Name *" />
                    <p class="mt-1 text-xs text-foreground/60">Complete official name of the school or family</p>
                    <x-ui::input id="full_name" name="full_name" type="text" class="mt-1 block w-full"
                        :value="old('full_name', $school->full_name ?? '')" required />
                    <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="display_name" value="NOVA School/Family Name *" />
                    <p class="mt-1 text-xs text-foreground/60">Name used within NOVA for this school or family</p>
                    <x-ui::input id="display_name" name="display_name" type="text" class="mt-1 block w-full"
                        :value="old('display_name', $school->display_name ?? '')" required />
                    <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="address" value="Address" />
                <p class="mt-1 text-xs text-foreground/60">Physical address of the school or family (optional)</p>
                <textarea id="address" name="address" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('address', $school->address ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="state" value="State *" />
                    <p class="mt-1 text-xs text-foreground/60">US state where the school or family is located</p>
                    <x-ui::select id="state" name="state" class="mt-1" placeholder="Select State">
                        <option value="">Select State</option>
                        @foreach ($states as $code => $label)
                            <option value="{{ $code }}" @selected(old('state', $school?->state_code ?? '') === $code)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="timezone" value="Time Zone *" />
                    <p class="mt-1 text-xs text-foreground/60">Timezone for scheduling and time conversions</p>
                    <x-ui::select id="timezone" name="timezone" class="mt-1" placeholder="Select Time Zone">
                        <option value="">Select Time Zone</option>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected(old('timezone', $school->timezone ?? '') === $tz)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="manager_id" value="Manager *" />
                    <p class="mt-1 text-xs text-foreground/60">Assigned manager for this school or family</p>
                    <x-ui::select id="manager_id" name="manager_id" class="mt-1" placeholder="Select Manager">
                        <option value="">Select Manager</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}" @selected(old('manager_id', $school->manager_id ?? $defaultManagerId) == $manager->id)>
                                {{ $manager->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
                </div>
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: Contact Information --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Contact Information</h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="contact_first_name" value="Primary Contact First Name" />
                    <p class="mt-1 text-xs text-foreground/60">First name of the primary school or family contact</p>
                    <x-ui::input id="contact_first_name" name="contact_first_name" type="text"
                        class="mt-1 block w-full" :value="old('contact_first_name', $school->contact_first_name ?? '')" />
                    <x-input-error :messages="$errors->get('contact_first_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="contact_last_name" value="Primary Contact Last Name" />
                    <p class="mt-1 text-xs text-foreground/60">Last name of the primary school or family contact</p>
                    <x-ui::input id="contact_last_name" name="contact_last_name" type="text"
                        class="mt-1 block w-full" :value="old('contact_last_name', $school->contact_last_name ?? '')" />
                    <x-input-error :messages="$errors->get('contact_last_name')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="contact_phone" value="Phone Number" />
                    <p class="mt-1 text-xs text-foreground/60">Contact phone number (format: 123-456-7890)</p>
                    <x-ui::input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full"
                        placeholder="123-456-7890" :value="old('contact_phone', $school->contact_phone ?? '')" data-phone-input />
                    <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="contact_email" value="Email Address" />
                    <p class="mt-1 text-xs text-foreground/60">Primary contact email address</p>
                    <x-ui::input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full"
                        :value="old('contact_email', $school->contact_email ?? '')" />
                    <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="invoice_email" value="Invoice Email" />
                <p class="mt-1 text-xs text-foreground/60">Email address where invoices should be sent</p>
                <x-ui::input id="invoice_email" name="invoice_email" type="email" class="mt-1 block w-full"
                    :value="old('invoice_email', $school->invoice_email ?? '')" />
                <x-input-error :messages="$errors->get('invoice_email')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section C: School/Family Characteristics --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">School/Family Characteristics</h3>
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <x-input-label for="school_type" value="School/Family Type *" />
                    <p class="mt-1 text-xs text-foreground/60">Type of educational institution or family placement</p>
                    <x-ui::select id="school_type" name="school_type" class="mt-1"
                        placeholder="Select school/family type">
                        <option value="">Select school/family type</option>
                        @foreach ($schoolTypes as $type)
                            <option value="{{ $type }}" @selected(old('school_type', $school->school_type ?? '') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('school_type')" class="mt-2" />
                </div>

                <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                    <x-input-label value="Private Student?" />
                    <p class="mt-1 text-xs text-foreground/60 mb-3">Check if this record is a private family (not a
                        district-enrolled school).</p>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_private_student" value="0">
                        <input id="is_private_student" name="is_private_student" type="checkbox" value="1"
                            class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                            @checked(old('is_private_student', $school->is_private_student ?? false))>
                        <label for="is_private_student" class="text-sm font-medium text-foreground/80 cursor-pointer">
                            Private students only (family; not district school)
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('is_private_student')" class="mt-2" />
                </div>

                <div id="is_auto_extend_section" class="p-4 border border-border rounded-lg bg-muted"
                     style="{{ old('is_private_student', $school->is_private_student ?? false) ? '' : 'display:none' }}">
                    <x-input-label value="Auto-Extend Contract & SSAs?" />
                    <p id="is_auto_extend_help" class="mt-1 text-xs text-foreground/60 mb-3">
                        When enabled, this school's active contract and all active SSAs will be automatically
                        extended by 1 year on their expiry date. The assigned manager will be notified by email.
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_auto_extend" value="0">
                        <input id="is_auto_extend" name="is_auto_extend" type="checkbox" value="1"
                               class="w-4 h-4 rounded border-border text-primary focus:ring-primary"
                               aria-describedby="is_auto_extend_help"
                               @checked(old('is_auto_extend', $school->is_auto_extend ?? false))>
                        <label for="is_auto_extend" class="text-sm font-medium text-foreground/80 cursor-pointer">
                            Auto-extend contract and SSAs annually
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('is_auto_extend')" class="mt-2" />
                </div>

                <div class="p-4 border border-border rounded-lg bg-muted">
                    <x-input-label value="Past session submission?" />
                    <p id="non_billable_scheduling_help" class="mt-1 text-xs text-foreground/60 mb-3">
                        Use when therapists should not submit post-session logs in Nova for this school/family.
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="non_billable_scheduling" value="0">
                        <input id="non_billable_scheduling" name="non_billable_scheduling" type="checkbox"
                            value="1" class="w-4 h-4 rounded border-border text-primary focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
                            aria-describedby="non_billable_scheduling_help"
                            @checked(old('non_billable_scheduling', $school->non_billable_scheduling ?? false))>
                        <label for="non_billable_scheduling"
                            class="text-sm font-medium text-foreground/80 cursor-pointer">
                            Exclude from Past Sessions submission queue
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('non_billable_scheduling')" class="mt-2" />
                </div>

                <div class="p-4 border border-border rounded-lg bg-muted">
                    <x-input-label value="Allow weekend scheduling?" />
                    <p id="allow_weekend_scheduling_help" class="mt-1 text-xs text-foreground/60 mb-3">
                        When enabled, therapists can schedule sessions on Saturdays and Sundays for this school's students.
                    </p>
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="allow_weekend_scheduling" value="0">
                        <input id="allow_weekend_scheduling" name="allow_weekend_scheduling" type="checkbox"
                            value="1" class="w-4 h-4 rounded border-border text-primary focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring"
                            aria-describedby="allow_weekend_scheduling_help"
                            @checked(old('allow_weekend_scheduling', $school->allow_weekend_scheduling ?? false))>
                        <label for="allow_weekend_scheduling"
                            class="text-sm font-medium text-foreground/80 cursor-pointer">
                            Allow Saturday/Sunday scheduling
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('allow_weekend_scheduling')" class="mt-2" />
                </div>
            </div>

            <div>
                <x-input-label for="external_emr_name" value="External EMR School/Family Name" />
                <p class="mt-1 text-xs text-foreground/60">Name used in external EMR system (if applicable)</p>
                <x-ui::input id="external_emr_name" name="external_emr_name" type="text"
                    class="mt-1 block w-full" :value="old('external_emr_name', $school->external_emr_name ?? '')" />
                <x-input-error :messages="$errors->get('external_emr_name')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Action Buttons --}}
    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.schools.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update School/Family' : 'Create School/Family' }}
        </x-ui::button>
    </div>
</form>
