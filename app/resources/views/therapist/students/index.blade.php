<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-foreground">My Students</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6">
                @if ($students->count() > 0)
                    <x-ui::table>
                        <x-slot name="head">
                            <tr>
                                <th class="px-4 py-3 border border-border">Name</th>
                                <th class="px-4 py-3 border border-border">Email</th>
                                <th class="px-4 py-3 border border-border">Grade Level</th>
                                <th class="px-4 py-3 border border-border">Date of Birth</th>
                                <th class="px-4 py-3 border border-border">Phone</th>
                                <th class="px-4 py-3 border border-border">Emergency Contact</th>
                                <th class="px-4 py-3 border border-border">Assigned Date</th>
                                <th class="px-4 py-3 border border-border">Assignment</th>
                                <th class="px-4 py-3 border border-border">User Status</th>
                                <th class="px-4 py-3 border border-border">Actions</th>
                            </tr>
                        </x-slot>
                        @foreach ($students as $student)
                            <tr>
                                <td class="px-4 py-3 border border-border">{{ $student->name }}</td>
                                <td class="px-4 py-3 border border-border">{{ $student->email }}</td>
                                <td class="px-4 py-3 border border-border">
                                    {{ $student->studentProfile?->grade_level ?? 'N/A' }}</td>
                                <td class="px-4 py-3 border border-border">
                                    {{ $student->studentProfile?->date_of_birth?->format('Y-m-d') ?? 'N/A' }}</td>
                                <td class="px-4 py-3 border border-border">
                                    {{ $student->studentProfile?->phone ?? 'N/A' }}</td>
                                <td class="px-4 py-3 border border-border">
                                    {{ $student->studentProfile?->emergency_contact ?? 'N/A' }}</td>
                                <td class="px-4 py-3 border border-border">
                                    {{ $student->pivot->assigned_at?->format('Y-m-d') ?? 'N/A' }}</td>
                                <td class="px-4 py-3 border border-border">
                                    @php $assignmentStatus = strtolower($student->pivot->status ?? 'active'); @endphp
                                    <x-ui::badge :variant="$assignmentStatus === 'active' ? 'success' : 'danger'">
                                        {{ ucfirst($assignmentStatus) }}
                                    </x-ui::badge>
                                </td>
                                <td class="px-4 py-3 border border-border">
                                    @php $userStatus = strtolower($student->status?->value ?? 'active'); @endphp
                                    <x-ui::badge :variant="$userStatus === 'active' ? 'success' : 'danger'">
                                        {{ ucfirst($userStatus) }}
                                    </x-ui::badge>
                                </td>
                                <td class="px-4 py-3 border border-border">
                                    @if (($student->status?->value ?? 'active') === 'active')
                                        <form method="POST"
                                            action="{{ route('therapist.students.deactivate', $student) }}"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui::button variant="danger" size="sm"
                                                type="submit">Deactivate</x-ui::button>
                                        </form>
                                    @else
                                        <form method="POST"
                                            action="{{ route('therapist.students.activate', $student) }}"
                                            class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui::button variant="success" size="sm"
                                                type="submit">Activate</x-ui::button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui::table>

                    <div class="mt-4">
                        {{ $students->links() }}
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-foreground/70 mb-4">No students assigned yet.</p>
                        <a href="{{ route('therapist.students.create') }}"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                            Add Your First Student
                        </a>
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
