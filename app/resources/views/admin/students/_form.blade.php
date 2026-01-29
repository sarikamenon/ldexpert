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
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                    :value="old('first_name', $profile?->first_name)" dusk="student-first-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="middle_name" value="Middle Name" />
                <p class="mt-1 text-xs text-foreground/60">Student's middle name (optional)</p>
                <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full"
                    :value="old('middle_name', $profile?->middle_name)" />
                <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" value="Last Name *" />
                <p class="mt-1 text-xs text-foreground/60">Student's last name</p>
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $profile?->last_name)"
                    dusk="student-last-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="email" value="Email *" />
                <p class="mt-1 text-xs text-foreground/60">Email address for account access</p>
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $isEdit ? $student->email : '')"
                    dusk="student-email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="gender" value="Gender *" />
                <p class="mt-1 text-xs text-foreground/60">Student's gender identity</p>
                <select id="gender" name="gender"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    <option value="">Select Gender</option>
                    @foreach ($genderOptions as $gender)
                        <option value="{{ $gender }}" @selected(old('gender', $profile?->gender) === $gender)>
                            {{ $gender }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="date_of_birth" value="Date of Birth *" />
                <p class="mt-1 text-xs text-foreground/60">Student's date of birth</p>
                <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
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
                <x-ui::select name="school_id" id="school_id" :searchable="false" class="mt-1">
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
                <x-input-label for="id_number" value="Student ID *" />
                <p class="mt-1 text-xs text-foreground/60">Unique student identifier from the school</p>
                <x-text-input id="id_number" name="id_number" type="text" class="mt-1 block w-full"
                    :value="old('id_number', $profile?->id_number)" />
                <x-input-error :messages="$errors->get('id_number')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="timezone" value="Timezone *" />
                <p class="mt-1 text-xs text-foreground/60">Timezone for scheduling and time conversions</p>
                <x-ui::select name="timezone" id="timezone" :searchable="false" class="mt-1">
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
                <x-input-label for="grade_level" value="Grade Level *" />
                <p class="mt-1 text-xs text-foreground/60">Current grade level (e.g., K, 1, 2, 3-12, or other)</p>
                <x-text-input id="grade_level" name="grade_level" type="text" class="mt-1 block w-full"
                    :value="old('grade_level', $profile?->grade_level)" />
                <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section C: Parent / Guardian (advanced) --}}
    <x-ui::card class="p-6 space-y-4" x-data="{ open: false }">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-foreground">Parent / Guardian Information</h3>
            <button type="button"
                class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-base px-2 py-1"
                @click="open = !open" x-bind:aria-expanded="open.toString()">
                <span x-show="!open">Show</span>
                <span x-show="open">Hide</span>
            </button>
        </div>

        <div x-show="open" x-cloak class="space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="parent_guardian_name" value="Name" />
                    <p class="mt-1 text-xs text-foreground/60">Parent or guardian's full name</p>
                    <x-text-input id="parent_guardian_name" name="parent_guardian_name" type="text"
                        class="mt-1 block w-full" :value="old('parent_guardian_name', $profile?->parent_guardian_name)" />
                    <x-input-error :messages="$errors->get('parent_guardian_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="parent_guardian_email" value="Email" />
                    <p class="mt-1 text-xs text-foreground/60">Parent or guardian's email address</p>
                    <x-text-input id="parent_guardian_email" name="parent_guardian_email" type="email"
                        class="mt-1 block w-full" :value="old('parent_guardian_email', $profile?->parent_guardian_email)" />
                    <x-input-error :messages="$errors->get('parent_guardian_email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="parent_guardian_phone" value="Phone" />
                    <p class="mt-1 text-xs text-foreground/60">Contact phone number (format: 123-456-7890)</p>
                    <x-text-input id="parent_guardian_phone" name="parent_guardian_phone" type="text"
                        class="mt-1 block w-full" placeholder="123-456-7890" :value="old('parent_guardian_phone', $profile?->parent_guardian_phone)" data-phone-input />
                    <x-input-error :messages="$errors->get('parent_guardian_phone')" class="mt-2" />
                </div>
            </div>
    </x-ui::card>

    {{-- Section D: Address (advanced) --}}
    <x-ui::card class="p-6 space-y-4" x-data="{ open: true }">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-foreground">Address Information</h3>
            <button type="button"
                class="text-sm text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-base px-2 py-1"
                @click="open = !open" x-bind:aria-expanded="open.toString()">
                <span x-show="!open">Show</span>
                <span x-show="open">Hide</span>
            </button>
        </div>

        <div x-show="open" x-cloak class="space-y-4">

            <div>
                <x-input-label for="address" value="Address" />
                <p class="mt-1 text-xs text-foreground/60">Street address (optional)</p>
                <textarea id="address" name="address" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('address', $profile?->address) }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="city" value="City *" />
                    <p class="mt-1 text-xs text-foreground/60">City name</p>
                    <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                        :value="old('city', $profile?->city)" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="state" value="State *" />
                    <p class="mt-1 text-xs text-foreground/60">US state</p>
                    <select name="state" id="state"
                        class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">
                        <option value="">Select State</option>
                        @foreach ($states as $code => $name)
                            <option value="{{ $code }}" @selected(old('state', $profile?->state) === $code)>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="zip_code" value="ZIP Code *" />
                    <p class="mt-1 text-xs text-foreground/60">ZIP or postal code</p>
                    <x-text-input id="zip_code" name="zip_code" type="text" class="mt-1 block w-full"
                        :value="old('zip_code', $profile?->zip_code)" />
                    <x-input-error :messages="$errors->get('zip_code')" class="mt-2" />
                </div>
            </div>
    </x-ui::card>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.students.index') }}"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
            Cancel
        </a>
        <x-primary-button>
            {{ $isEdit ? 'Update Student Info' : 'Create Student' }}
        </x-primary-button>
    </div>
</form>
