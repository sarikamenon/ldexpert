@props([
    'students',
    'filters' => [],
    'schools' => [],
    'statuses' => [],
    'showMetrics' => false,
    'metrics' => null,
    /**
     * When set, table uses server-side DataTables: empty tbody, data loaded via AJAX from this URL.
     */
    'datatableUrl' => null,
    /**
     * Context controls both layout and routing:
     * - 'index'  : admin index page
     * - 'detail' : admin student detail tab
     * - 'therapist' : therapist-facing students list
     */
    'context' => 'index',
    'schoolId' => null,
    'therapistId' => null,
])

@if ($showMetrics && $metrics)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Students</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Active</p>
            <p class="text-3xl font-semibold mt-1 text-success">{{ $metrics['active'] ?? 0 }}</p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Deactivated</p>
            <p class="text-3xl font-semibold mt-1 text-danger">{{ $metrics['inactive'] ?? 0 }}</p>
        </x-ui::card>
    </div>
@endif

<x-ui::card class="p-6 space-y-4">
    <x-ui::filter-toolbar formId="studentsFiltersForm">
        <x-slot:filters>
            @if ($context === 'detail')
                <input type="hidden" name="tab" value="students">
            @endif

            @if ($schoolId)
                <input type="hidden" name="school_id" value="{{ $schoolId }}">
            @endif
            @if ($therapistId)
                <input type="hidden" name="therapist_id" value="{{ $therapistId }}">
            @endif

            <x-ui::input type="text" name="search" class="w-64" placeholder="Search students"
                value="{{ $filters['search'] ?? '' }}" />

            <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ ucfirst($status->value) }}
                    </option>
                @endforeach
            </x-ui::select>

            @if (!empty($schools))
                <x-ui::select name="school_id" searchable placeholder="All Schools" :inline="true">
                    <option value="">All Schools</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected((int) ($filters['school_id'] ?? 0) === $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>
            @endif

            @if ($context === 'therapist' && !empty($filters))
                <a href="{{ route('therapist.students.index') }}">
                    <x-ui::button variant="secondary">
                        Clear
                    </x-ui::button>
                </a>
            @endif
        </x-slot:filters>

        <x-slot:actions>
            @if ($context !== 'therapist')
                <a href="{{ route('admin.students.export', $filters) }}" id="exportStudentsButton">
                    <x-ui::button variant="secondary">
                        Export
                    </x-ui::button>
                </a>
                @if ($context === 'index' || $context === 'detail')
                    <a href="{{ route('admin.students.create') }}">
                        <x-ui::button>
                            Add Student
                        </x-ui::button>
                    </a>
                @endif
            @endif
        </x-slot:actions>
    </x-ui::filter-toolbar>

    @if (isset($datatableUrl) || $students->count() > 0)
        <div class="overflow-x-auto">
            <table id="studentsTable" class="w-full display" @if(isset($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>School</th>
                        <th>Grade Level</th>
                        <th class="w-40">Date of Birth</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!isset($datatableUrl))
                    @foreach ($students as $student)
                        @php
                            $profile = $student->studentProfile;
                            $isActive = ($student->status?->value ?? 'inactive') === 'active';
                        @endphp
                        <tr>
                            <td>
                                @if ($context === 'therapist')
                                    <a href="{{ route('therapist.students.show', $student) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        title="View Student"
                                        aria-label="View student {{ $student->name }}">
                                        {{ $student->id }}
                                    </a>
                                @else
                                    <a href="{{ route('admin.students.show', $student) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        title="View Student"
                                        aria-label="View student {{ $student->name }}">
                                        {{ $student->id }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if ($context === 'therapist')
                                    <a href="{{ route('therapist.students.show', $student) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $student->name }}
                                    </a>
                                @else
                                    <a href="{{ route('admin.students.show', $student) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $student->name }}
                                    </a>
                                @endif
                            </td>
                            <td>{{ $student->username }}</td>
                            <td>{{ $student->email }}</td>
                            <td>
                                @if ($profile?->school)
                                    @if ($context === 'therapist')
                                        {{ $profile->school->display_name }}
                                    @else
                                        <a href="{{ route('admin.schools.show', $profile->school) }}"
                                            class="text-primary hover:underline">
                                            {{ $profile->school->display_name }}
                                        </a>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $profile?->grade_level ?? '—' }}</td>
                            <td class="w-40 whitespace-nowrap">
                                {{ optional($profile?->date_of_birth)->format('Y-m-d') ?? '—' }}
                            </td>
                            <td>
                                <x-ui::badge :variant="$isActive ? 'success' : 'danger'">
                                    {{ ucfirst($student->status?->value ?? 'inactive') }}
                                </x-ui::badge>
                            </td>
                            <td>
                                <div class="flex space-x-1">
                                    @if ($context === 'therapist')
                                        <a href="{{ route('therapist.students.show', $student) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors"
                                            title="View Student">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.students.show', $student) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors"
                                            title="View Student">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.students.edit', $student) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors"
                                            title="Edit Student" dusk="edit-student-{{ $student->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                </path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                </path>
                                            </svg>
                                        </a>

                                        <button type="button" data-student="{{ $student->id }}"
                                            data-status="{{ $student->status?->value ?? 'inactive' }}"
                                            dusk="student-status-toggle-{{ $student->id }}"
                                            class="toggle-student-status inline-flex items-center justify-center w-8 h-8 rounded transition-colors {{ $isActive ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                            title="{{ $isActive ? 'Deactivate Student' : 'Activate Student' }}">
                                            @if ($isActive)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6"
                                                        y2="18">
                                                    </line>
                                                    <line x1="6" y1="6" x2="18"
                                                        y2="18">
                                                    </line>
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            @endif
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    @else
        <x-ui::empty-state title="No students found."
            :description="$context === 'index' ? 'Try adjusting your filters or add a new student to get started.' : null"
            :action-label="$context === 'index' ? 'Add Student' : null"
            :action-href="$context === 'index' ? route('admin.students.create') : null" />
    @endif
</x-ui::card>
