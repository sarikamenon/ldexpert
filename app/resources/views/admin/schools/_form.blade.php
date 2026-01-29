<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">School Information</h3>
        <div>
            <x-input-label for="full_name" value="Full School Name *" />
            <p class="mt-1 text-xs text-foreground/60">Complete official name of the school</p>
            <x-ui::input id="full_name" name="full_name" type="text" class="mt-1 block w-full" :value="old('full_name', $school->full_name ?? '')"
                required />
            <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="display_name" value="NOVA School Name *" />
            <p class="mt-1 text-xs text-foreground/60">Name used within NOVA system for this school</p>
            <x-ui::input id="display_name" name="display_name" type="text" class="mt-1 block w-full"
                :value="old('display_name', $school->display_name ?? '')" required />
            <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="address" value="Address" />
            <p class="mt-1 text-xs text-foreground/60">Physical address of the school (optional)</p>
            <textarea id="address" name="address" rows="3"
                class="mt-1 block w-full border border-border rounded-lg shadow-sm focus:ring-primary focus:border-primary">{{ old('address', $school->address ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="state" value="State *" />
                <p class="mt-1 text-xs text-foreground/60">US state where the school is located</p>
                <x-ui::select id="state" name="state" :searchable="false" class="mt-1">
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
                <x-ui::select id="timezone" name="timezone" :searchable="false" class="mt-1">
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
                <p class="mt-1 text-xs text-foreground/60">Assigned manager for this school</p>
                <x-ui::select id="manager_id" name="manager_id" :searchable="false" class="mt-1">
                    <option value="">Select Manager</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}" @selected(old('manager_id', $school->manager_id ?? '') == $manager->id)>{{ $manager->name }}</option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Contact Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="contact_first_name" value="Primary Contact First Name" />
                <p class="mt-1 text-xs text-foreground/60">First name of the primary school contact</p>
                <x-ui::input id="contact_first_name" name="contact_first_name" type="text" class="mt-1 block w-full"
                    :value="old('contact_first_name', $school->contact_first_name ?? '')" />
                <x-input-error :messages="$errors->get('contact_first_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="contact_last_name" value="Primary Contact Last Name" />
                <p class="mt-1 text-xs text-foreground/60">Last name of the primary school contact</p>
                <x-ui::input id="contact_last_name" name="contact_last_name" type="text" class="mt-1 block w-full"
                    :value="old('contact_last_name', $school->contact_last_name ?? '')" />
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
    </x-ui::card>
</div>

<x-ui::card class="p-6 space-y-4 mt-6">
    <h3 class="text-lg font-semibold">School Characteristics</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <x-input-label for="school_type" value="School Type *" />
            <p class="mt-1 text-xs text-foreground/60">Type of educational institution</p>
            <x-ui::select id="school_type" name="school_type" :searchable="false" class="mt-1">
                @foreach ($schoolTypes as $type)
                    <option value="{{ $type }}" @selected(old('school_type', $school->school_type ?? '') === $type)>
                        {{ $type }}
                    </option>
                @endforeach
            </x-ui::select>
            <x-input-error :messages="$errors->get('school_type')" class="mt-2" />
        </div>
        <div class="mt-6">
            <x-ui::checkbox id="is_private_student" name="is_private_student" value="1"
                :checked="old('is_private_student', $school->is_private_student ?? false)"
                label="Is Private Student?" />
        </div>
        <div class="mt-6">
            <x-ui::checkbox id="non_billable_scheduling" name="non_billable_scheduling" value="1"
                :checked="old('non_billable_scheduling', $school->non_billable_scheduling ?? false)"
                label="Non Billable Scheduling?" />
        </div>
    </div>
    <div>
        <x-input-label for="external_emr_name" value="External EMR School Name" />
        <p class="mt-1 text-xs text-foreground/60">Name used in external EMR system (if applicable)</p>
        <x-ui::input id="external_emr_name" name="external_emr_name" type="text" class="mt-1 block w-full"
            :value="old('external_emr_name', $school->external_emr_name ?? '')" />
        <x-input-error :messages="$errors->get('external_emr_name')" class="mt-2" />
    </div>
</x-ui::card>
