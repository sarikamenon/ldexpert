<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground">View Student</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-6">
                {{-- Basic Information --}}
                <div>
                    <h3 class="text-lg font-semibold mb-3 pb-2 border-b">Basic Information</h3>
                    <div class="space-y-4">
                        {{-- Row 1: First Name, Middle Name --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-foreground/70">First Name</div>
                                <div class="font-medium">{{ $student->studentProfile?->first_name ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-foreground/70">Middle Name</div>
                                <div class="font-medium">{{ $student->studentProfile?->middle_name ?? 'N/A' }}</div>
                            </div>
                        </div>

                        {{-- Row 2: Last Name, Email --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-foreground/70">Last Name</div>
                                <div class="font-medium">{{ $student->studentProfile?->last_name ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-foreground/70">Email</div>
                                <div class="font-medium">{{ $student->email }}</div>
                            </div>
                        </div>

                        {{-- Row 3: Date of Birth, Gender --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-foreground/70">Date of Birth</div>
                                <div class="font-medium">
                                    {{ $student->studentProfile?->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-foreground/70">Gender</div>
                                <div class="font-medium">{{ $student->studentProfile?->gender ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- School Information --}}
                @if (
                    $student->studentProfile?->school ||
                        $student->studentProfile?->id_number ||
                        $student->studentProfile?->grade_level ||
                        $student->studentProfile?->timezone)
                    <div>
                        <h3 class="text-lg font-semibold mb-3 pb-2 border-b">School Information</h3>
                        <div class="space-y-4">
                            {{-- Row 1: School, ID Number --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-foreground/70">School</div>
                                    <div class="font-medium">{{ $student->studentProfile?->school ?? 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-foreground/70">ID Number</div>
                                    <div class="font-medium">{{ $student->studentProfile?->id_number ?? 'N/A' }}</div>
                                </div>
                            </div>

                            {{-- Row 2: Timezone, Grade Level --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-foreground/70">Timezone</div>
                                    <div class="font-medium">
                                        {{ $student->studentProfile?->timezone ? \App\Constants\UsTimezones::getTimezoneLabel($student->studentProfile->timezone) : 'N/A' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-foreground/70">Grade Level</div>
                                    <div class="font-medium">{{ $student->studentProfile?->grade_level ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Parent/Guardian Information --}}
                @if (
                    $student->studentProfile?->parent_guardian_name ||
                        $student->studentProfile?->parent_guardian_email ||
                        $student->studentProfile?->parent_guardian_phone)
                    <div>
                        <h3 class="text-lg font-semibold mb-3 pb-2 border-b">Parent/Guardian Information</h3>
                        <div class="space-y-4">
                            {{-- Row 1: Parent/Guardian Name (full width) --}}
                            <div>
                                <div class="text-sm text-foreground/70">Parent/Guardian Name</div>
                                <div class="font-medium">{{ $student->studentProfile?->parent_guardian_name ?? 'N/A' }}
                                </div>
                            </div>

                            {{-- Row 2: Parent/Guardian Email, Parent/Guardian Contact Phone --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-foreground/70">Parent/Guardian Email</div>
                                    <div class="font-medium">
                                        {{ $student->studentProfile?->parent_guardian_email ?? 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-foreground/70">Parent/Guardian Contact Phone</div>
                                    <div class="font-medium">
                                        {{ $student->studentProfile?->parent_guardian_phone ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Address Information --}}
                @if (
                    $student->studentProfile?->address ||
                        $student->studentProfile?->city ||
                        $student->studentProfile?->state ||
                        $student->studentProfile?->zip_code)
                    <div>
                        <h3 class="text-lg font-semibold mb-3 pb-2 border-b">Address Information</h3>
                        <div class="space-y-4">
                            {{-- Address (full width) --}}
                            <div>
                                <div class="text-sm text-foreground/70">Address</div>
                                <div class="font-medium">{{ $student->studentProfile?->address ?? 'N/A' }}</div>
                            </div>

                            {{-- City, State, Zip Code (3 columns) --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <div class="text-sm text-foreground/70">City</div>
                                    <div class="font-medium">{{ $student->studentProfile?->city ?? 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-sm text-foreground/70">State</div>
                                    <div class="font-medium">
                                        {{ $student->studentProfile?->state ? \App\Constants\UsStates::getStateName($student->studentProfile->state) : 'N/A' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm text-foreground/70">Zip Code</div>
                                    <div class="font-medium">{{ $student->studentProfile?->zip_code ?? 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
