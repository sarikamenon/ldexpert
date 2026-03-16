@php
    $isEdit = isset($student);
    $profile = $student->studentProfile ?? null;
    $genderOptions = ['Male', 'Female', 'Non-binary', 'Prefer not to say'];
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.students.update', $student) : route('admin.students.store') }}"
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
                <p class="mt-1 text-xs text-foreground/60">Student's first name</p>
                <x-ui::input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $profile?->first_name)"
                    dusk="student-first-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="middle_name" value="Middle Name" />
                <p class="mt-1 text-xs text-foreground/60">Student's middle name (optional)</p>
                <x-ui::input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full"
                    :value="old('middle_name', $profile?->middle_name)" />
                <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" value="Last Name *" />
                <p class="mt-1 text-xs text-foreground/60">Student's last name</p>
                <x-ui::input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $profile?->last_name)"
                    dusk="student-last-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="username" value="Username *" />
                <p class="mt-1 text-xs text-foreground/60" id="username_help">A unique username for student login. Letters, numbers, dots, and dashes only.</p>
                <x-ui::input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $isEdit ? $student->username : '')"
                    dusk="student-username" aria-describedby="username_help" />
                <x-input-error :messages="$errors->get('username')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email *" />
                <p class="mt-1 text-xs text-foreground/60">Email for notifications (can be shared with siblings)</p>
                <x-ui::input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $isEdit ? $student->email : '')"
                    dusk="student-email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="gender" value="Gender *" />
                <p class="mt-1 text-xs text-foreground/60">Student's gender identity</p>
                <x-ui::select id="gender" name="gender" class="mt-1" placeholder="Select Gender">
                    <option value="">Select Gender</option>
                    @foreach ($genderOptions as $gender)
                        <option value="{{ $gender }}" @selected(old('gender', $profile?->gender) === $gender)>
                            {{ $gender }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="date_of_birth" value="Date of Birth" />
                <p class="mt-1 text-xs text-foreground/60">Student's date of birth (optional)</p>
                <x-ui::input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                    :value="old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d'))" dusk="student-date-of-birth" />
                <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: School & Academic Info --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">School & Academic Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="school_id" value="School *" />
                <p class="mt-1 text-xs text-foreground/60">School where the student is enrolled</p>
                <x-ui::select name="school_id" id="school_id" class="mt-1" placeholder="Select School">
                    <option value="">Select School</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected((string) old('school_id', $profile?->school_id) === (string) $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="id_number" id="id_number_label" value="Student ID *" />
                <p class="mt-1 text-xs text-foreground/60" id="id_number_help">Required for non-private schools. Auto-generated for private schools if left blank.</p>
                <x-ui::input id="id_number" name="id_number" type="text" class="mt-1 block w-full"
                    :value="old('id_number', $profile?->id_number)" aria-describedby="id_number_help" />
                <x-input-error :messages="$errors->get('id_number')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="timezone" value="Timezone *" />
                <p class="mt-1 text-xs text-foreground/60">Timezone for scheduling and time conversions</p>
                <x-ui::select name="timezone" id="timezone" class="mt-1" placeholder="Select Timezone">
                    <option value="">Select Timezone</option>
                    @foreach ($timezones as $tz => $label)
                        <option value="{{ $tz }}" @selected(old('timezone', $profile?->timezone) === $tz)>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="grade_level" value="Grade Level" />
                <p class="mt-1 text-xs text-foreground/60">Current grade level (e.g., K, 1, 2, 3-12, or other) (optional)</p>
                <x-ui::input id="grade_level" name="grade_level" type="text" class="mt-1 block w-full"
                    :value="old('grade_level', $profile?->grade_level)" />
                <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section C: Parent / Guardian Information --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Parent / Guardian Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="parent_guardian_name" value="Name" />
                <p class="mt-1 text-xs text-foreground/60">Parent or guardian's full name</p>
                <x-ui::input id="parent_guardian_name" name="parent_guardian_name" type="text"
                    class="mt-1 block w-full" :value="old('parent_guardian_name', $profile?->parent_guardian_name)" />
                <x-input-error :messages="$errors->get('parent_guardian_name')" class="mt-2" />
            </div>

            <div>
                <div class="flex items-center gap-3">
                    <x-input-label for="parent_guardian_email" value="Email" />
                    <label class="flex items-center gap-2 text-xs text-foreground/70 cursor-pointer">
                        <input type="checkbox" id="parent_email_same_as_notification" class="rounded border-gray-300 text-primary focus:ring-primary h-3.5 w-3.5" />
                         Same as notification email
                    </label>
                </div>
                <p class="mt-1 text-xs text-foreground/60">Parent or guardian's email address</p>
                <x-ui::input id="parent_guardian_email" name="parent_guardian_email" type="email"
                    class="mt-1 block w-full" :value="old('parent_guardian_email', $profile?->parent_guardian_email)" />
                <x-input-error :messages="$errors->get('parent_guardian_email')" class="mt-2" />
            </div>

            <div>
                <div class="flex items-center gap-3">
                    <x-input-label for="schedule_email" value="Schedule Email" />
                    <label class="flex items-center gap-2 text-xs text-foreground/70 cursor-pointer">
                        <input type="checkbox" id="schedule_email_same_as_notification" class="rounded border-gray-300 text-primary focus:ring-primary h-3.5 w-3.5" />
                        Same as notification email
                    </label>
                </div>
                <p class="mt-1 text-xs text-foreground/60">Schedule email for reminders</p>
                <x-text-input id="schedule_email" name="schedule_email" type="email"
                    class="mt-1 block w-full" :value="old('schedule_email', $profile?->schedule_email)" />
                <x-input-error :messages="$errors->get('schedule_email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="parent_guardian_phone" value="Phone" />
                <p class="mt-1 text-xs text-foreground/60">Contact phone number (format: 123-456-7890)</p>
                <x-ui::input id="parent_guardian_phone" name="parent_guardian_phone" type="text"
                    class="mt-1 block w-full" placeholder="123-456-7890" :value="old('parent_guardian_phone', $profile?->parent_guardian_phone)" data-phone-input />
                <x-input-error :messages="$errors->get('parent_guardian_phone')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section D: Address Information --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Address Information</h3>

        <div>
            <x-input-label for="address" value="Address" />
            <p class="mt-1 text-xs text-foreground/60">Street address (optional)</p>
            <textarea id="address" name="address" rows="3"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('address', $profile?->address) }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="city" value="City" />
                <p class="mt-1 text-xs text-foreground/60">City name (optional)</p>
                <x-ui::input id="city" name="city" type="text" class="mt-1 block w-full"
                    :value="old('city', $profile?->city)" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="state" value="State" />
                <p class="mt-1 text-xs text-foreground/60">US state (optional)</p>
                <x-ui::select name="state" id="state" class="mt-1" placeholder="Select State">
                    <option value="">Select State</option>
                    @foreach ($states as $code => $name)
                        <option value="{{ $code }}" @selected(old('state', $profile?->state) === $code)>
                            {{ $name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('state')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="zip_code" value="ZIP Code" />
                <p class="mt-1 text-xs text-foreground/60">ZIP or postal code (optional)</p>
                <x-ui::input id="zip_code" name="zip_code" type="text" class="mt-1 block w-full"
                    :value="old('zip_code', $profile?->zip_code)" />
                <x-input-error :messages="$errors->get('zip_code')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    <script type="application/json" id="private-schools-data">
        @json($schools->where('is_private_student', true)->pluck('id'))
    </script>

    <input type="hidden" name="redirect_to_ssa" id="redirect_to_ssa" value="0" />

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.students.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Student Info' : 'Create Student' }}
        </x-ui::button>
        @unless ($isEdit)
            <x-ui::button type="submit" id="create-and-add-ssa-btn">
                Create and Add SSA
            </x-ui::button>
        @endunless
    </div>
</form>
