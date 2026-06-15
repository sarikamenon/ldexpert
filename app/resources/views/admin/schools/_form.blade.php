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
        <h3 class="text-lg font-semibold mb-6">School / family characteristics</h3>

        <div class="space-y-6">
            {{-- Details subsection --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-xs font-semibold tracking-wider text-foreground/60 uppercase">Details</span>
                    <span class="flex-1 h-px bg-border"></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="school_type" value="School / family type *" />
                        <x-ui::select id="school_type" name="school_type" class="mt-1"
                            placeholder="Select type">
                            <option value="">Select type</option>
                            @foreach ($schoolTypes as $type)
                                <option value="{{ $type }}" @selected(old('school_type', $school->school_type ?? '') === $type)>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </x-ui::select>
                        <p class="mt-1 text-xs text-foreground/60">Educational institution or family placement</p>
                        <x-input-error :messages="$errors->get('school_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="external_emr_name" value="External EMR name" />
                        <x-ui::input id="external_emr_name" name="external_emr_name" type="text"
                            class="mt-1 block w-full" :value="old('external_emr_name', $school->external_emr_name ?? '')" />
                        <p class="mt-1 text-xs text-foreground/60">Name in external EMR system, if any</p>
                        <x-input-error :messages="$errors->get('external_emr_name')" class="mt-2" />
                    </div>
                </div>
            </div>

            {{-- Settings subsection --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <span class="text-xs font-semibold tracking-wider text-foreground/60 uppercase">Settings</span>
                    <span class="flex-1 h-px bg-border"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-6">
                    <x-ui::checkbox-row
                        name="is_private_student"
                        label="Private student"
                        subtext="Family record, not a district school"
                        tooltip="Check if this record is a private family rather than a district-enrolled school."
                        :checked="old('is_private_student', $school->is_private_student ?? false)"
                    />

                    {{-- Wrapper kept for the existing JS toggler that shows/hides this row based on is_private_student --}}
                    <div id="is_auto_extend_section"
                         style="{{ old('is_private_student', $school->is_private_student ?? false) ? '' : 'display:none' }}">
                        <x-ui::checkbox-row
                            name="is_auto_extend"
                            label="Auto-extend contract and SSAs"
                            subtext="Extend by 1 year on expiry date"
                            tooltip="When enabled, the active contract and all active SSAs are automatically extended by 1 year on their expiry date. The assigned manager is notified by email."
                            :checked="old('is_auto_extend', $school->is_auto_extend ?? false)"
                        />
                    </div>

                    <x-ui::checkbox-row
                        name="non_billable_scheduling"
                        label="Exclude from past sessions queue"
                        subtext="Skip post-session log submission"
                        tooltip="Use when therapists should not submit post-session logs in Nova for this school or family."
                        :checked="old('non_billable_scheduling', $school->non_billable_scheduling ?? false)"
                    />

                    <x-ui::checkbox-row
                        name="allow_weekend_scheduling"
                        label="Allow weekend scheduling"
                        subtext="Saturdays and Sundays available"
                        tooltip="When enabled, therapists can schedule sessions on Saturdays and Sundays for this school's students."
                        :checked="old('allow_weekend_scheduling', $school->allow_weekend_scheduling ?? false)"
                    />
                </div>
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
