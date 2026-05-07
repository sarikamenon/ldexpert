@php
    $isEdit = isset($lead);
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.leads.update', $lead) : route('admin.leads.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Section A: Basic Information --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold">Basic Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="first_name" value="First Name *" />
                <p class="mt-1 text-xs text-foreground/60" id="first_name_help">Lead's first name</p>
                <x-ui::input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                    :value="old('first_name', $lead->first_name ?? '')" aria-describedby="first_name_help" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="middle_name" value="Middle Name" />
                <p class="mt-1 text-xs text-foreground/60" id="middle_name_help">Middle name (optional)</p>
                <x-ui::input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full"
                    :value="old('middle_name', $lead->middle_name ?? '')" aria-describedby="middle_name_help" />
                <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" value="Last Name *" />
                <p class="mt-1 text-xs text-foreground/60" id="last_name_help">Lead's last name</p>
                <x-ui::input id="last_name" name="last_name" type="text" class="mt-1 block w-full"
                    :value="old('last_name', $lead->last_name ?? '')" aria-describedby="last_name_help" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="email" value="Email" />
                <p class="mt-1 text-xs text-foreground/60" id="email_help">Contact email for the lead (optional at this stage)</p>
                <x-ui::input id="email" name="email" type="email" class="mt-1 block w-full"
                    :value="old('email', $lead->email ?? '')" aria-describedby="email_help" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="gender" value="Gender" />
                <p class="mt-1 text-xs text-foreground/60" id="gender_help">Student's gender identity (optional)</p>
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

            <div>
                <x-input-label for="date_of_birth" value="Date of Birth" />
                <p class="mt-1 text-xs text-foreground/60" id="dob_help">Student's date of birth (optional)</p>
                <x-ui::input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                    :value="old('date_of_birth', isset($lead) ? $lead->date_of_birth?->format('Y-m-d') : '')" aria-describedby="dob_help" />
                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: School/Family & Academic Info --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">School/Family & Academic Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="school_id" value="School/Family" />
                <p class="mt-1 text-xs text-foreground/60" id="school_help">School or family of interest (optional at lead stage)</p>
                <x-ui::select name="school_id" id="school_id" class="mt-1" placeholder="Select School/Family" aria-describedby="school_help">
                    <option value="">Select School/Family</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) old('school_id', $lead->school_id ?? '') === (string) $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="grade_level" value="Grade Level" />
                <p class="mt-1 text-xs text-foreground/60" id="grade_help">Current or expected grade level (optional)</p>
                <x-ui::input id="grade_level" name="grade_level" type="text" class="mt-1 block w-full"
                    :value="old('grade_level', $lead->grade_level ?? '')" aria-describedby="grade_help" />
                <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section C: Parent / Guardian Information --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Parent / Guardian Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="parent_guardian_name" value="Name" />
                <p class="mt-1 text-xs text-foreground/60" id="pg_name_help">Parent or guardian's full name</p>
                <x-ui::input id="parent_guardian_name" name="parent_guardian_name" type="text"
                    class="mt-1 block w-full" :value="old('parent_guardian_name', $lead->parent_guardian_name ?? '')"
                    aria-describedby="pg_name_help" />
                <x-input-error :messages="$errors->get('parent_guardian_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="parent_guardian_email" value="Email" />
                <p class="mt-1 text-xs text-foreground/60" id="pg_email_help">Parent or guardian's email address</p>
                <x-ui::input id="parent_guardian_email" name="parent_guardian_email" type="email"
                    class="mt-1 block w-full" :value="old('parent_guardian_email', $lead->parent_guardian_email ?? '')"
                    aria-describedby="pg_email_help" />
                <x-input-error :messages="$errors->get('parent_guardian_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="parent_guardian_phone" value="Phone" />
                <p class="mt-1 text-xs text-foreground/60" id="pg_phone_help">Contact phone (format: 123-456-7890)</p>
                <x-ui::input id="parent_guardian_phone" name="parent_guardian_phone" type="text"
                    class="mt-1 block w-full" placeholder="123-456-7890"
                    :value="old('parent_guardian_phone', $lead->parent_guardian_phone ?? '')"
                    data-phone-input aria-describedby="pg_phone_help" />
                <x-input-error :messages="$errors->get('parent_guardian_phone')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section D: Address Information --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Address Information</h3>

        <div>
            <x-input-label for="address" value="Address" />
            <p class="mt-1 text-xs text-foreground/60" id="address_help">Street address (optional)</p>
            <textarea id="address" name="address" rows="3" aria-describedby="address_help"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('address', $lead->address ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="city" value="City" />
                <p class="mt-1 text-xs text-foreground/60" id="city_help">City name (optional at lead stage)</p>
                <x-ui::input id="city" name="city" type="text" class="mt-1 block w-full"
                    :value="old('city', $lead->city ?? '')" aria-describedby="city_help" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="state" value="State" />
                <p class="mt-1 text-xs text-foreground/60" id="state_help">US state (optional at lead stage)</p>
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
                <p class="mt-1 text-xs text-foreground/60" id="zip_help">ZIP or postal code (optional at lead stage)</p>
                <x-ui::input id="zip_code" name="zip_code" type="text" class="mt-1 block w-full"
                    :value="old('zip_code', $lead->zip_code ?? '')" aria-describedby="zip_help" />
                <x-input-error :messages="$errors->get('zip_code')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section E: Lead Pipeline --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Lead Pipeline</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="source" value="Source" />
                <p class="mt-1 text-xs text-foreground/60" id="source_help">How this lead was acquired</p>
                <x-ui::select id="source" name="source" class="mt-1" placeholder="Select Source" aria-describedby="source_help">
                    <option value="">Select Source</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}" @selected(old('source', isset($lead) ? $lead->source?->value : '') === $source->value)>
                            {{ $source->label() }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('source')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="follow_up_date" value="Follow-up Date" />
                <p class="mt-1 text-xs text-foreground/60" id="followup_help">Date to follow up with this lead</p>
                <x-ui::input id="follow_up_date" name="follow_up_date" type="date" class="mt-1 block w-full"
                    :value="old('follow_up_date', isset($lead) ? $lead->follow_up_date?->format('Y-m-d') : '')"
                    aria-describedby="followup_help" />
                <x-input-error :messages="$errors->get('follow_up_date')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="follow_up_notes" value="Follow-up Notes" />
            <p class="mt-1 text-xs text-foreground/60" id="followup_notes_help">Notes about what to follow up on when you reach out again.</p>
            <textarea id="follow_up_notes" name="follow_up_notes" rows="8" aria-describedby="followup_notes_help"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('follow_up_notes', $lead->follow_up_notes ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('follow_up_notes')" class="mt-2" />
        </div>
    </x-ui::card>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.leads.index') }}">
            <x-ui::button variant="secondary">Cancel</x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Lead' : 'Create Lead' }}
        </x-ui::button>
    </div>
</form>
