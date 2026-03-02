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
                <span class="text-foreground/60">School</span>
                <p class="font-medium">{{ $lead->school?->display_name ?? '—' }}</p>
            </div>
            <div>
                <span class="text-foreground/60">Parent/Guardian</span>
                <p class="font-medium">{{ $lead->parent_guardian_name ?? '—' }}</p>
            </div>
        </div>
        <p class="mt-4 text-xs text-foreground/60">Name, date of birth, parent/guardian info, and address will be carried over from the lead. Fill in the required fields below to create the student account.</p>
    </x-ui::card>

    <form method="POST" action="{{ route('admin.leads.convert.store', $lead) }}" class="space-y-6">
        @csrf

        {{-- Required Student Fields --}}
        <x-ui::card class="p-6 space-y-4">
            <h3 class="text-lg font-semibold">Student Account Details</h3>
            <p class="text-sm text-foreground/60">These fields are required to create a student account. A password will be auto-generated.</p>

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
                    <x-input-label for="school_id" value="School *" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_help">School where the student is enrolled</p>
                    <x-ui::select name="school_id" id="school_id" class="mt-1" placeholder="Select School" aria-describedby="school_help">
                        <option value="">Select School</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected((string) old('school_id', $lead->school_id) === (string) $school->id)>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('school_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="id_number" value="Student ID *" />
                    <p class="mt-1 text-xs text-foreground/60" id="id_number_help">Unique student identifier from the school</p>
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
                            <option value="{{ $tz }}" @selected(old('timezone') === $tz)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="grade_level" value="Grade Level *" />
                    <p class="mt-1 text-xs text-foreground/60" id="grade_help">Current grade level</p>
                    <x-ui::input id="grade_level" name="grade_level" type="text" class="mt-1 block w-full"
                        :value="old('grade_level', $lead->grade_level ?? '')" aria-describedby="grade_help" />
                    <x-input-error :messages="$errors->get('grade_level')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="gender" value="Gender *" />
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
                    <x-input-label for="city" value="City *" />
                    <p class="mt-1 text-xs text-foreground/60" id="city_help">City name</p>
                    <x-ui::input id="city" name="city" type="text" class="mt-1 block w-full"
                        :value="old('city', $lead->city ?? '')" aria-describedby="city_help" />
                    <x-input-error :messages="$errors->get('city')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="state" value="State *" />
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
                    <x-input-label for="zip_code" value="ZIP Code *" />
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

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.leads.show', $lead) }}">
                <x-ui::button variant="secondary">Cancel</x-ui::button>
            </a>
            <x-ui::button type="submit">Convert to Student</x-ui::button>
        </div>
    </form>
</x-admin.layouts.app>
