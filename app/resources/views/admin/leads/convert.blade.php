<x-admin.layouts.app>
    <x-page-title title="Convert Lead to Student" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Lead Summary --}}
    <x-ui::card class="p-6 mb-6">
        <h3 class="text-lg font-semibold mb-3">Lead Information (Read-only)</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-foreground/60">Name</span>
                <p class="font-medium">{{ $lead->full_name }}</p>
            </div>
            <div>
                <span class="text-foreground/60">Email</span>
                <p class="font-medium">{{ $lead->email ?? '—' }}</p>
            </div>
            <div>
                <span class="text-foreground/60">School/Family</span>
                <p class="font-medium">{{ $lead->school?->display_name ?? '—' }}</p>
            </div>
            <div>
                <span class="text-foreground/60">Parent/Guardian</span>
                <p class="font-medium">{{ $lead->parent_guardian_name ?? '—' }}</p>
            </div>
        </div>
        <p class="mt-4 text-xs text-foreground/60">Name, date of birth, parent/guardian info, and address will be carried over from the lead. Fill in the required fields below to create the student account.</p>
    </x-ui::card>

    @if (session('error'))
        <x-ui::alert variant="error" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <form method="POST" action="{{ route('admin.leads.convert.store', $lead) }}" class="space-y-6"
        data-lead-last-name="{{ $lead->last_name }}"
        data-lead-parent-name="{{ $lead->parent_guardian_name }}"
        data-lead-parent-email="{{ $lead->parent_guardian_email }}"
        data-lead-parent-phone="{{ $lead->parent_guardian_phone }}"
        data-lead-address="{{ $lead->address }}">
        @csrf

        {{-- Required Student Fields --}}
        <x-ui::card class="p-6 space-y-4">
            <h3 class="text-lg font-semibold">Student Account Details</h3>
            <p class="text-sm text-foreground/60">Username, email, and timezone are required. A password will be auto-generated.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="username" value="Username *" />
                    <p class="mt-1 text-xs text-foreground/60" id="username_help">Unique login username. Letters, numbers, dots, dashes only.</p>
                    <x-ui::input id="username" name="username" type="text" class="mt-1 block w-full"
                        :value="old('username')" aria-describedby="username_help" />
                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="Email *" />
                    <p class="mt-1 text-xs text-foreground/60" id="email_help">Student's email for notifications and login</p>
                    <x-ui::input id="email" name="email" type="email" class="mt-1 block w-full"
                        :value="old('email', $lead->email ?? '')" aria-describedby="email_help" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="school_id" value="School/Family" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_help">Pick an existing school/family, or leave empty to create a new one below.</p>
                    <x-ui::select name="school_id" id="school_id" class="mt-1" placeholder="Select School/Family" aria-describedby="school_help">
                        <option value="">Select School/Family</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) old('school_id', $lead->school_id) === (string) $school->id)>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="id_number" value="Student ID" />
                    <p class="mt-1 text-xs text-foreground/60" id="id_number_help">Required for non-private schools. Auto-generated for private families if left blank.</p>
                    <x-ui::input id="id_number" name="id_number" type="text" class="mt-1 block w-full"
                        :value="old('id_number')" aria-describedby="id_number_help" />
                    <x-input-error :messages="$errors->get('id_number')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="timezone" value="Timezone *" />
                    <p class="mt-1 text-xs text-foreground/60" id="tz_help">Timezone for scheduling</p>
                    <x-ui::select name="timezone" id="timezone" class="mt-1" placeholder="Select Timezone" aria-describedby="tz_help">
                        <option value="">Select Timezone</option>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected(old('timezone', 'America/New_York') === $tz)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="grade_level" value="Grade Level" />
                    <p class="mt-1 text-xs text-foreground/60" id="grade_help">Current grade level</p>
                    <x-ui::input id="grade_level" name="grade_level" type="text" class="mt-1 block w-full"
                        :value="old('grade_level', $lead->grade_level ?? '')" aria-describedby="grade_help" />
                    <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="gender" value="Gender" />
                    <p class="mt-1 text-xs text-foreground/60" id="gender_help">Student's gender identity</p>
                    <x-ui::select id="gender" name="gender" class="mt-1" placeholder="Select Gender" aria-describedby="gender_help">
                        <option value="">Select Gender</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender }}" @selected(old('gender', $lead->gender ?? '') === $gender)>
                                {{ $gender }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="city" value="City" />
                    <p class="mt-1 text-xs text-foreground/60" id="city_help">City name</p>
                    <x-ui::input id="city" name="city" type="text" class="mt-1 block w-full"
                        :value="old('city', $lead->city ?? '')" aria-describedby="city_help" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="state" value="State" />
                    <p class="mt-1 text-xs text-foreground/60" id="state_help">US state</p>
                    <x-ui::select name="state" id="state" class="mt-1" placeholder="Select State" aria-describedby="state_help">
                        <option value="">Select State</option>
                        @foreach ($states as $code => $name)
                            <option value="{{ $code }}" @selected(old('state', $lead->state ?? '') === $code)>
                                {{ $name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="zip_code" value="ZIP Code" />
                    <p class="mt-1 text-xs text-foreground/60" id="zip_help">ZIP or postal code</p>
                    <x-ui::input id="zip_code" name="zip_code" type="text" class="mt-1 block w-full"
                        :value="old('zip_code', $lead->zip_code ?? '')" aria-describedby="zip_help" />
                    <x-input-error :messages="$errors->get('zip_code')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="schedule_email" value="Schedule Email" />
                    <p class="mt-1 text-xs text-foreground/60" id="sched_help">Email for schedule reminders (optional)</p>
                    <x-ui::input id="schedule_email" name="schedule_email" type="email" class="mt-1 block w-full"
                        :value="old('schedule_email')" aria-describedby="sched_help" />
                    <x-input-error :messages="$errors->get('schedule_email')" class="mt-2" />
                </div>
            </div>
        </x-ui::card>

        {{-- New School/Family Details (shown when no existing school is picked; collapsed by default) --}}
        <x-ui::card id="new_family_panel" class="p-6"
            style="{{ old('school_id', $lead->school_id) ? 'display:none' : '' }}">
            <button type="button" id="new_family_toggle"
                class="flex w-full items-center justify-between text-left"
                aria-expanded="{{ $errors->has('family_name') || $errors->has('family_state') || $errors->has('family_timezone') || $errors->has('family_school_type') ? 'true' : 'false' }}"
                aria-controls="new_family_body">
                <span>
                    <span class="block text-lg font-semibold">New School/Family Details</span>
                    <span class="block text-sm text-foreground/60">No existing school selected — a new one will be created from this lead. Click to review and edit.</span>
                </span>
                <svg id="new_family_chevron" class="w-5 h-5 shrink-0 text-foreground/60 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div id="new_family_body" class="mt-4 space-y-6"
                style="{{ $errors->has('family_name') || $errors->has('family_state') || $errors->has('family_timezone') || $errors->has('family_school_type') ? '' : 'display:none' }}">

                {{-- School/Family Information --}}
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="family_full_name" value="Full School/Family Name *" />
                            <p class="mt-1 text-xs text-foreground/60">Complete official name of the school or family</p>
                            <x-ui::input id="family_full_name" name="family_full_name" type="text" class="mt-1 block w-full"
                                :value="old('family_full_name')" />
                            <x-input-error :messages="$errors->get('family_full_name')" class="mt-2" />
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <x-input-label for="family_name" value="NOVA School/Family Name *" />
                                <label for="family_same_as_full_name" class="flex items-center gap-1 text-xs text-foreground/70 cursor-pointer">
                                    <input id="family_same_as_full_name" type="checkbox"
                                        class="w-3.5 h-3.5 rounded border-input text-primary focus:ring-ring focus-visible:ring-2 focus-visible:ring-ring">
                                    Same as Full Name
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-foreground/60">Name used within NOVA for this school or family</p>
                            <x-ui::input id="family_name" name="family_name" type="text" class="mt-1 block w-full"
                                :value="old('family_name')" />
                            <x-input-error :messages="$errors->get('family_name')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="family_address" value="Address" />
                        <p class="mt-1 text-xs text-foreground/60">Physical address of the school or family (optional)</p>
                        <textarea id="family_address" name="family_address" rows="3"
                            class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('family_address', $lead->address ?? '') }}</textarea>
                        <x-input-error :messages="$errors->get('family_address')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="family_state" value="State *" />
                            <p class="mt-1 text-xs text-foreground/60">US state where the school or family is located</p>
                            <x-ui::select name="family_state" id="family_state" class="mt-1" placeholder="Select State">
                                <option value="">Select State</option>
                                @foreach ($states as $code => $name)
                                    <option value="{{ $code }}" @selected(old('family_state', $lead->state ?? '') === $code)>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </x-ui::select>
                            <x-input-error :messages="$errors->get('family_state')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="family_timezone" value="Time Zone *" />
                            <p class="mt-1 text-xs text-foreground/60">Timezone for scheduling and time conversions</p>
                            <x-ui::select name="family_timezone" id="family_timezone" class="mt-1" placeholder="Select Time Zone">
                                <option value="">Select Time Zone</option>
                                @foreach ($timezones as $tz => $label)
                                    <option value="{{ $tz }}" @selected(old('family_timezone', 'America/New_York') === $tz)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-ui::select>
                            <x-input-error :messages="$errors->get('family_timezone')" class="mt-2" />
                        </div>
                    </div>
                </div>

                {{-- Contact Information --}}
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-xs font-semibold tracking-wider text-foreground/60 uppercase">Contact Information</span>
                        <span class="flex-1 h-px bg-border"></span>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="family_contact_first_name" value="Primary Contact First Name" />
                                <x-ui::input id="family_contact_first_name" name="family_contact_first_name" type="text"
                                    class="mt-1 block w-full" :value="old('family_contact_first_name')" />
                                <x-input-error :messages="$errors->get('family_contact_first_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="family_contact_last_name" value="Primary Contact Last Name" />
                                <x-ui::input id="family_contact_last_name" name="family_contact_last_name" type="text"
                                    class="mt-1 block w-full" :value="old('family_contact_last_name')" />
                                <x-input-error :messages="$errors->get('family_contact_last_name')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="family_contact_phone" value="Phone Number" />
                                <p class="mt-1 text-xs text-foreground/60">Contact phone number (format: 123-456-7890)</p>
                                <x-ui::input id="family_contact_phone" name="family_contact_phone" type="text"
                                    class="mt-1 block w-full" placeholder="123-456-7890"
                                    :value="old('family_contact_phone', $lead->parent_guardian_phone ?? '')" />
                                <x-input-error :messages="$errors->get('family_contact_phone')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="family_contact_email" value="Email Address" />
                                <p class="mt-1 text-xs text-foreground/60">Primary contact email address</p>
                                <x-ui::input id="family_contact_email" name="family_contact_email" type="email"
                                    class="mt-1 block w-full" :value="old('family_contact_email', $lead->parent_guardian_email ?? '')" />
                                <x-input-error :messages="$errors->get('family_contact_email')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="family_invoice_email" value="Invoice Email" />
                            <p class="mt-1 text-xs text-foreground/60">Email address where invoices should be sent</p>
                            <x-ui::input id="family_invoice_email" name="family_invoice_email" type="email"
                                class="mt-1 block w-full" :value="old('family_invoice_email')" />
                            <x-input-error :messages="$errors->get('family_invoice_email')" class="mt-2" />
                        </div>
                    </div>
                </div>

                {{-- Characteristics: Details --}}
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-xs font-semibold tracking-wider text-foreground/60 uppercase">Details</span>
                        <span class="flex-1 h-px bg-border"></span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="family_school_type" value="School / family type *" />
                            <x-ui::select id="family_school_type" name="family_school_type" class="mt-1" placeholder="Select type">
                                @foreach ($schoolTypes as $type)
                                    <option value="{{ $type->value }}" @selected(old('family_school_type', \App\Enums\SchoolType::VIRTUAL->value) === $type->value)>
                                        {{ $type->value }}
                                    </option>
                                @endforeach
                            </x-ui::select>
                            <p class="mt-1 text-xs text-foreground/60">Educational institution or family placement</p>
                            <x-input-error :messages="$errors->get('family_school_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="family_external_emr_name" value="External EMR name" />
                            <x-ui::input id="family_external_emr_name" name="family_external_emr_name" type="text"
                                class="mt-1 block w-full" :value="old('family_external_emr_name')" />
                            <p class="mt-1 text-xs text-foreground/60">Name in external EMR system, if any</p>
                            <x-input-error :messages="$errors->get('family_external_emr_name')" class="mt-2" />
                        </div>
                    </div>
                </div>

                {{-- Characteristics: Settings --}}
                <div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-xs font-semibold tracking-wider text-foreground/60 uppercase">Settings</span>
                        <span class="flex-1 h-px bg-border"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-6">
                        <x-ui::checkbox-row
                            name="create_private_family"
                            id="create_private_family"
                            label="Private student"
                            subtext="Family record, not a district school"
                            tooltip="Check if this record is a private family rather than a district-enrolled school. Student ID is auto-generated for private families."
                            :checked="old('create_private_family', false)"
                        />

                        <x-ui::checkbox-row
                            name="family_is_auto_extend"
                            label="Auto-extend contract and SSAs"
                            subtext="Extend by 1 year on expiry date"
                            :checked="old('family_is_auto_extend', false)"
                        />

                        <x-ui::checkbox-row
                            name="family_non_billable_scheduling"
                            label="Exclude from past sessions queue"
                            subtext="Skip post-session log submission"
                            :checked="old('family_non_billable_scheduling', false)"
                        />

                        <x-ui::checkbox-row
                            name="family_allow_weekend_scheduling"
                            label="Allow weekend scheduling"
                            subtext="Saturdays and Sundays available"
                            :checked="old('family_allow_weekend_scheduling', false)"
                        />
                    </div>
                </div>
            </div>{{-- /#new_family_body --}}
        </x-ui::card>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.leads.show', $lead) }}">
                <x-ui::button variant="secondary">Cancel</x-ui::button>
            </a>
            <x-ui::button type="submit">Convert to Student</x-ui::button>
        </div>
    </form>

    @vite(['resources/js/pages/admin-leads-convert.js'])
</x-admin.layouts.app>
