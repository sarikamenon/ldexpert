<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-foreground">Add Student</h2>
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
                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Full Name</label>
                            <x-ui::input name="name" required />
                            @error('name')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Email</label>
                            <x-ui::input type="email" name="email" required />
                            @error('email')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm text-foreground/70 mb-1">Temporary Password</label>
                            <x-ui::input type="text" name="password" value="{{ Str::random(12) }}" required />
                            @error('password')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="pt-2">
                            <x-ui::button type="submit">Create Student</x-ui::button>
                        </div>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>

    <script>
        $(function() {
            $('#student-create-form').on('submit', function() {
                // Placeholder for future AJAX if needed
            });
        });
    </script>
</x-app-layout>
