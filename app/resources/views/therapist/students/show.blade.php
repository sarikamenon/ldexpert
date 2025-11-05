<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground">Student Details</h2>
            <div class="flex gap-3">
                <a href="{{ route('therapist.students.index') }}" class="text-primary hover:text-primary/80">Back</a>
                <a href="{{ route('therapist.students.edit', $student) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">Edit</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 lg:px-8">
            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <div>
                    <div class="text-sm text-foreground/70">Name</div>
                    <div class="font-medium">{{ $student->name }}</div>
                </div>
                <div>
                    <div class="text-sm text-foreground/70">Email</div>
                    <div class="font-medium">{{ $student->email }}</div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-sm text-foreground/70">Grade Level</div>
                        <div class="font-medium">{{ $student->studentProfile?->grade_level ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-foreground/70">Date of Birth</div>
                        <div class="font-medium">
                            {{ $student->studentProfile?->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-foreground/70">Phone</div>
                        <div class="font-medium">{{ $student->studentProfile?->phone ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-foreground/70">Emergency Contact</div>
                        <div class="font-medium">{{ $student->studentProfile?->emergency_contact ?? 'N/A' }}</div>
                    </div>
                </div>
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
