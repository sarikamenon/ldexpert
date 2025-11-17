<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <x-page-title title="Edit Student">
                <x-slot name="actions">
                    <a href="{{ route('therapist.students.show', $student) }}"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-gray-50">
                        Back
                    </a>
                </x-slot>
            </x-page-title>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6">
                <form method="POST" action="{{ route('therapist.students.update', $student) }}">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-4">
                        {{-- Basic Information --}}
                        <h3 class="text-lg font-semibold mb-4">Basic Information</h3>

                        {{-- Row 1: First Name, Middle Name --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">First Name <span
                                        class="text-red-500">*</span></label>
                                <x-ui::input name="first_name"
                                    value="{{ old('first_name', $student->studentProfile?->first_name) }}" required />
                                @error('first_name')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Middle Name</label>
                                <x-ui::input name="middle_name"
                                    value="{{ old('middle_name', $student->studentProfile?->middle_name) }}" />
                                @error('middle_name')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 2: Last Name, Email --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Last Name <span
                                        class="text-red-500">*</span></label>
                                <x-ui::input name="last_name"
                                    value="{{ old('last_name', $student->studentProfile?->last_name) }}" required />
                                @error('last_name')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Email <span
                                        class="text-red-500">*</span></label>
                                <x-ui::input type="email" name="email" value="{{ old('email', $student->email) }}"
                                    required />
                                @error('email')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 3: Date of Birth, Gender --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Date of Birth</label>
                                <x-ui::input type="date" name="date_of_birth"
                                    value="{{ old('date_of_birth', optional($student->studentProfile?->date_of_birth)->format('Y-m-d')) }}"
                                    max="{{ now()->format('Y-m-d') }}" />
                                @error('date_of_birth')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Gender</label>
                                <x-ui::input name="gender"
                                    value="{{ old('gender', $student->studentProfile?->gender) }}" />
                                @error('gender')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- School Information --}}
                        <div class="pt-4 border-t">
                            <h3 class="text-lg font-semibold mb-4">School Information</h3>
                        </div>

                        {{-- Row 1: School, ID Number --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">School</label>
                                <x-ui::input name="school"
                                    value="{{ old('school', $student->studentProfile?->school) }}" />
                                @error('school')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">ID Number</label>
                                <x-ui::input name="id_number"
                                    value="{{ old('id_number', $student->studentProfile?->id_number) }}" />
                                @error('id_number')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 2: Timezone, Grade Level --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Timezone</label>
                                <select name="timezone"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                    <option value="">Select Timezone</option>
                                    @foreach (\App\Constants\UsTimezones::getTimezones() as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ old('timezone', $student->studentProfile?->timezone) == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('timezone')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Grade Level</label>
                                <x-ui::input name="grade_level"
                                    value="{{ old('grade_level', $student->studentProfile?->grade_level) }}"
                                    placeholder="e.g., K, 1, 2, 3..." />
                                @error('grade_level')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Parent/Guardian Information --}}
                        <div class="pt-4 border-t">
                            <h3 class="text-lg font-semibold mb-4">Parent/Guardian Information</h3>
                        </div>

                        {{-- Row 1: Parent/Guardian Name (full width) --}}
                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Parent/Guardian Name</label>
                            <x-ui::input name="parent_guardian_name"
                                value="{{ old('parent_guardian_name', $student->studentProfile?->parent_guardian_name) }}" />
                            @error('parent_guardian_name')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Row 2: Parent/Guardian Email, Parent/Guardian Contact Phone --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Parent/Guardian Email</label>
                                <x-ui::input type="email" name="parent_guardian_email"
                                    value="{{ old('parent_guardian_email', $student->studentProfile?->parent_guardian_email) }}" />
                                @error('parent_guardian_email')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Parent/Guardian Contact
                                    Phone</label>
                                <x-ui::input type="tel" name="parent_guardian_phone"
                                    value="{{ old('parent_guardian_phone', $student->studentProfile?->parent_guardian_phone) }}" />
                                @error('parent_guardian_phone')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <h3 class="text-lg font-semibold mb-4">Address Information</h3>
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Address</label>
                            <x-ui::input name="address"
                                value="{{ old('address', $student->studentProfile?->address) }}" />
                            @error('address')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">City</label>
                                <x-ui::input name="city"
                                    value="{{ old('city', $student->studentProfile?->city) }}" />
                                @error('city')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">State</label>
                                <select name="state"
                                    class="w-full rounded-lg border-gray-300 focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                                    <option value="">Select State</option>
                                    @foreach (\App\Constants\UsStates::getStates() as $code => $name)
                                        <option value="{{ $code }}"
                                            {{ old('state', $student->studentProfile?->state) == $code ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('state')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm text-foreground/70 mb-1">Zip Code</label>
                                <x-ui::input name="zip_code"
                                    value="{{ old('zip_code', $student->studentProfile?->zip_code) }}" />
                                @error('zip_code')
                                    <div class="text-danger text-sm mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <x-ui::button type="submit">Save Changes</x-ui::button>
                            <a href="{{ route('therapist.students.show', $student) }}"
                                class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-gray-50">Cancel</a>
                        </div>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
