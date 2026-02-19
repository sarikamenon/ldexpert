@php
    $isEdit = isset($therapist);
    $profile = $isEdit ? $therapist->therapistProfile : null;
@endphp

<form method="POST"
    action="{{ $isEdit ? route('admin.therapists.update', $therapist) : route('admin.therapists.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Section A: Employment & Identity --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Employment & Identity</h3>

        <div class="space-y-4">
            {{-- Employee Type --}}
            <div>
                {{-- Temporarily hide employee type selection; default all therapists to 1099 --}}
                <input type="hidden" name="employee_type"
                    value="{{ old('employee_type', $profile?->employee_type?->value ?? \App\Enums\EmployeeType::CONTRACTOR_1099->value) }}">
                <x-input-error :messages="$errors->get('employee_type')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Title --}}
                <div>
                    <x-input-label for="title" value="Title *" />
                    <p class="mt-1 text-xs text-foreground/60">Professional title (e.g., BCBA, RBT)</p>
                    <x-ui::select name="title" id="title" class="mt-1" placeholder="Select Title">
                        <option value="">Select Title</option>
                        @foreach ($titles as $title)
                            <option value="{{ $title->value }}"
                                {{ old('title', $profile?->title?->value) === $title->value ? 'selected' : '' }}>
                                {{ $title->value }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                {{-- First Name --}}
                <div>
                    <x-input-label for="first_name" value="First Name *" />
                    <p class="mt-1 text-xs text-foreground/60">Therapist's first name</p>
                    <x-ui::input id="first_name" name="first_name" type="text" class="mt-1 block w-full"
                        :value="old('first_name', $profile?->first_name)" dusk="therapist-first-name" />
                    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                </div>
            </div>

            {{-- Last Name --}}
            <div>
                <x-input-label for="last_name" value="Last Name *" />
                <p class="mt-1 text-xs text-foreground/60">Therapist's last name</p>
                <x-ui::input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $profile?->last_name)"
                    dusk="therapist-last-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section B: Contact & Account Details --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Contact & Account Details</h3>

        <div class="space-y-4">
            {{-- Personal Email --}}
            <div>
                <x-input-label for="personal_email" value="Personal Email *" />
                <p class="mt-1 text-xs text-foreground/60">Primary email address for account access and communications
                </p>
                <x-ui::input id="personal_email" name="personal_email" type="email" class="mt-1 block w-full"
                    :value="old('personal_email', $profile?->personal_email ?? ($isEdit ? $therapist->email : ''))" dusk="therapist-personal-email" />
                <x-input-error :messages="$errors->get('personal_email')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Phone --}}
                <div>
                    <x-input-label for="phone" value="Phone *" />
                    <p class="mt-1 text-xs text-foreground/60">Contact phone number (format: 123-456-7890)</p>
                    <x-ui::input id="phone" name="phone" type="text" class="mt-1 block w-full"
                        placeholder="123-456-7890" :value="old('phone', $profile?->phone)" dusk="therapist-phone" data-phone-input />
                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                </div>

                {{-- NOVA Email --}}
                <div>
                    <x-input-label for="ld_email" value="NOVA Email" />
                    <p class="mt-1 text-xs text-foreground/60">Optional NOVA organization email address</p>
                    <x-ui::input id="ld_email" name="ld_email" type="email" class="mt-1 block w-full"
                        :value="old('ld_email', $profile?->ld_email)" dusk="therapist-ld-email" />
                    <x-input-error :messages="$errors->get('ld_email')" class="mt-2" />
                </div>
            </div>

            {{-- Address --}}
            <div>
                <x-input-label for="address" value="Address" />
                <p class="mt-1 text-xs text-foreground/60">Physical address (optional)</p>
                <textarea id="address" name="address" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('address', $profile?->address) }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>

            {{-- Default Meeting Location --}}
            <div>
                <x-input-label for="default_meeting_location" value="Default Meeting Location/Link" />
                <p class="mt-1 text-xs text-foreground/60">This will be auto-populated when creating a new schedule.
                    Enter default meeting link (e.g., Google Meet, Zoom) or address for schedules.</p>
                <textarea id="default_meeting_location" name="default_meeting_location" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    placeholder="Enter default meeting link (e.g., Google Meet, Zoom) or address for schedules">{{ old('default_meeting_location', $profile?->default_meeting_location) }}</textarea>
                <x-input-error :messages="$errors->get('default_meeting_location')" class="mt-2" />
            </div>

            {{-- Comments --}}
            <div>
                <x-input-label for="comments" value="Internal Comments" />
                <p class="mt-1 text-xs text-foreground/60">Internal notes visible only to administrators</p>
                <textarea id="comments" name="comments" rows="3"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm">{{ old('comments', $profile?->comments) }}</textarea>
                <x-input-error :messages="$errors->get('comments')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    {{-- Section C: Professional Details --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Professional Details</h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Position --}}
                <div>
                    <x-input-label for="position" value="Position *" />
                    <p class="mt-1 text-xs text-foreground/60">Therapist's professional position</p>
                    <x-ui::select name="position" id="position" class="mt-1" placeholder="Select Position">
                        <option value="">Select Position</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->name }}"
                                {{ old('position', $profile?->position) === $position->name ? 'selected' : '' }}>
                                {{ $position->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('position')" class="mt-2" />
                </div>

                {{-- State --}}
                <div>
                    <x-input-label for="state" value="State Residing *" />
                    <p class="mt-1 text-xs text-foreground/60">US state where the therapist resides</p>
                    <x-ui::select name="state" id="state" class="mt-1" placeholder="Select State">
                        <option value="">Select State</option>
                        @foreach ($states as $code => $name)
                            <option value="{{ $code }}"
                                {{ old('state', $profile?->state) === $code ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('state')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Timezone --}}
                <div>
                    <x-input-label for="timezone" value="Timezone *" />
                    <p class="mt-1 text-xs text-foreground/60">Timezone for scheduling and time conversions</p>
                    <x-ui::select name="timezone" id="timezone" class="mt-1" placeholder="Select Timezone">
                        <option value="">Select Timezone</option>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}"
                                {{ old('timezone', $profile?->timezone) === $tz ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>

                {{-- Manager --}}
                <div>
                    <x-input-label for="manager_id" value="Therapist Manager *" />
                    <p class="mt-1 text-xs text-foreground/60">Assigned manager for this therapist</p>
                    <x-ui::select name="manager_id" id="manager_id" class="mt-1" placeholder="Select Manager">
                        <option value="">Select Manager</option>
                        @foreach ($managers as $manager)
                            <option value="{{ $manager->id }}"
                                {{ old('manager_id', $profile?->manager_id) == $manager->id ? 'selected' : '' }}>
                                {{ $manager->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Max Weekly Hours --}}
                <div>
                    <x-input-label for="max_weekly_hours" value="Max Weekly Hours *" />
                    <p class="mt-1 text-xs text-foreground/60">Maximum hours per week this therapist can work (maximum
                        40)</p>
                    <x-ui::input id="max_weekly_hours" name="max_weekly_hours" type="number" min="1"
                        max="40" step="1" class="mt-1 block w-full" :value="old('max_weekly_hours', $profile?->max_weekly_hours ?? 40)"
                        placeholder="e.g. 40" dusk="therapist-max-weekly-hours" />
                    <x-input-error :messages="$errors->get('max_weekly_hours')" class="mt-2" />
                </div>

                {{-- Date of Birth --}}
                <div>
                    <x-input-label for="dob" value="Date of Birth" />
                    <p class="mt-1 text-xs text-foreground/60">Optional date of birth</p>
                    <x-ui::input id="dob" name="dob" type="date" class="mt-1 block w-full"
                        :value="old('dob', $profile?->dob?->format('Y-m-d'))" />
                    <x-input-error :messages="$errors->get('dob')" class="mt-2" />
                </div>
            </div>
        </div>
    </x-ui::card>

    {{-- Action Buttons --}}
    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.therapists.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Therapist Info' : 'Create Therapist' }}
        </x-ui::button>
    </div>
</form>
