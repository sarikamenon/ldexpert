<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground">Add Student</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 lg:px-8">
            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6">
                <form method="POST" action="{{ route('therapist.students.store') }}" id="student-create-form">
                    @csrf
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold mb-4">Basic Information</h3>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Full Name</label>
                            <x-ui::input name="name" value="{{ old('name') }}" required />
                            @error('name')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Email</label>
                            <x-ui::input type="email" name="email" value="{{ old('email') }}" required />
                            @error('email')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>



                        <div class="pt-4 border-t">
                            <h3 class="text-lg font-semibold mb-4">Profile Information</h3>
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Date of Birth</label>
                            <x-ui::input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" />
                            @error('date_of_birth')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Grade Level</label>
                            <x-ui::input name="grade_level" value="{{ old('grade_level') }}"
                                placeholder="e.g., K, 1, 2, 3..." />
                            @error('grade_level')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Phone</label>
                            <x-ui::input type="tel" name="phone" value="{{ old('phone') }}" />
                            @error('phone')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Emergency Contact</label>
                            <x-ui::input type="tel" name="emergency_contact"
                                value="{{ old('emergency_contact') }}" />
                            @error('emergency_contact')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Parent (Optional)</label>
                            <select name="parent_id"
                                class="w-full rounded-lg border border-border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="">Select a parent (optional)</option>
                                @foreach ($parents as $parent)
                                    <option value="{{ $parent->id }}"
                                        {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }} ({{ $parent->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="pt-4 flex gap-3">
                            <x-ui::button type="submit">Create Student</x-ui::button>
                            <a href="{{ route('therapist.students.index') }}"
                                class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-gray-50">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
