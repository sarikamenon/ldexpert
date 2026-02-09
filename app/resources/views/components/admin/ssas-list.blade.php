@props([
    'ssas',
    'filters' => [],
    'statuses' => [],
    'students' => [],
    'therapists' => [],
    'services' => [],
    'showMetrics' => false,
    'metrics' => null,
    // context: 'index', 'detail', or 'therapist'
    'context' => 'index',
])

@if ($showMetrics && $metrics)
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total SSAs</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Pending</p>
            <p class="text-3xl font-semibold mt-1 text-warning">{{ $metrics['pending'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Active</p>
            <p class="text-3xl font-semibold mt-1 text-success">{{ $metrics['active'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Completed</p>
            <p class="text-3xl font-semibold mt-1 text-primary">{{ $metrics['completed'] ?? 0 }}</p>
        </x-ui::card>
    </div>
@endif

<x-ui::card class="p-6 space-y-6">
    <x-ui::filter-toolbar formId="ssaFiltersForm">
        <x-slot:filters>
            @if ($context === 'detail')
                <input type="hidden" name="tab" value="ssas">
            @endif

            <x-ui::input type="text" name="search" class="w-56" placeholder="Search SSAs"
                value="{{ $filters['search'] ?? '' }}" />

            <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-ui::select>

            @if ($context !== 'therapist')
                <x-ui::select name="student_id" searchable placeholder="All Students" :inline="true">
                    <option value="">All Students</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(($filters['student_id'] ?? null) == $student->id)>
                            {{ $student->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="therapist_id" searchable placeholder="All Therapists" :inline="true">
                    <option value="">All Therapists</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="service_id" searchable placeholder="All Services" :inline="true">
                    <option value="">All Services</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(($filters['service_id'] ?? null) == $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>
            @endif
        </x-slot:filters>

        <x-slot:actions>
            @if ($context !== 'therapist')
                @php
                    $exportFilters = $filters;
                    if ($context === 'detail') {
                        unset($exportFilters['tab']);
                    }
                @endphp
                <a href="{{ route('admin.ssas.export', $exportFilters) }}">
                    <x-ui::button variant="secondary">
                        Export
                    </x-ui::button>
                </a>
                @if ($context === 'index' || $context === 'detail')
                    <a href="{{ route('admin.ssas.create') }}">
                        <x-ui::button>
                            Add SSA
                        </x-ui::button>
                    </a>
                @endif
            @endif
        </x-slot:actions>
    </x-ui::filter-toolbar>

    @if ($ssas->count() > 0)
        <div class="overflow-x-auto">
            <table id="ssasTable" class="w-full display">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student & Service</th>
                        <th>Therapist</th>
                        <th style="min-width: 180px;">Date Range</th>
                        <th>Session Details</th>
                        <th>Minutes & Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ssas as $ssa)
                        <tr>
                            <td>
                                @if ($context === 'therapist')
                                    <a href="{{ route('therapist.ssas.show', $ssa) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors"
                                        title="View SSA Details">
                                        {{ $ssa->id }}
                                    </a>
                                @else
                                    <a href="{{ route('admin.ssas.show', $ssa) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors"
                                        title="View SSA Details">
                                        {{ $ssa->id }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col">
                                    @if ($ssa->student)
                                        @if ($context === 'therapist')
                                            <a href="{{ route('therapist.students.show', $ssa->student) }}"
                                                class="font-medium text-primary hover:underline">
                                                {{ $ssa->student->name }}
                                            </a>
                                        @else
                                            <a href="{{ route('admin.students.show', $ssa->student) }}"
                                                class="font-medium text-primary hover:underline">
                                                {{ $ssa->student->name }}
                                            </a>
                                        @endif
                                    @else
                                        <span class="font-medium text-foreground/50">Unknown Student</span>
                                    @endif
                                    <span
                                        class="text-sm text-foreground/70">{{ $ssa->primaryService->name ?? '—' }}</span>
                                    @if ($ssa->additionalServices->isNotEmpty())
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($ssa->additionalServices as $service)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full bg-background/subtle text-xs font-medium text-foreground/70">
                                                    {{ $service->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($ssa->student?->studentProfile?->school)
                                        @if ($context === 'therapist')
                                            <span class="text-xs text-foreground/60 mt-1">
                                                {{ $ssa->student->studentProfile->school->display_name }}
                                            </span>
                                        @else
                                            <a href="{{ route('admin.schools.show', $ssa->student->studentProfile->school) }}"
                                                class="text-xs text-foreground/60 hover:text-primary mt-1">
                                                {{ $ssa->student->studentProfile->school->display_name }}
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if ($ssa->assignedTherapist)
                                    <a href="{{ route('admin.therapists.show', $ssa->assignedTherapist) }}"
                                        class="text-primary hover:underline">
                                        {{ $ssa->assignedTherapist->name }}
                                    </a>
                                @elseif ($context !== 'therapist')
                                    <x-ui::button type="button" class="assign-therapist-btn" size="sm"
                                        data-ssa-id="{{ $ssa->id }}" title="Assign Therapist">
                                        Assign
                                    </x-ui::button>
                                @else
                                    <span class="text-sm text-foreground/60">Unassigned</span>
                                @endif
                            </td>
                            <td style="min-width: 180px;">
                                <div class="flex flex-col space-y-1">
                                    <span
                                        class="text-sm text-foreground">{{ $ssa->start_date->format('M d, Y') }}</span>
                                    <span class="text-sm text-foreground">{{ $ssa->end_date->format('M d, Y') }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-foreground/60 font-medium">Minutes:</span>
                                        <span class="text-sm text-foreground">{{ $ssa->minutes_per_session }} x
                                            {{ $ssa->sessions_per_frequency }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-foreground/60 font-medium">Frequency:</span>
                                        <span class="text-sm text-foreground">{{ $ssa->frequency->label() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col space-y-2">
                                    <div class="flex flex-col space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-foreground/60 font-medium">THO:</span>
                                            <span
                                                class="text-sm text-foreground font-medium">{{ number_format($ssa->tho_minutes) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-foreground/60 font-medium">Served:</span>
                                            <span
                                                class="text-sm text-foreground">{{ number_format($ssa->served_minutes) }}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <x-ui::badge :variant="match ($ssa->status) {
                                            \App\Enums\SSAStatus::ACTIVE => 'success',
                                            \App\Enums\SSAStatus::PENDING => 'warning',
                                            \App\Enums\SSAStatus::COMPLETED => 'primary',
                                            \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                                            default => 'secondary',
                                        }">
                                            {{ $ssa->status->label() }}
                                        </x-ui::badge>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    @if ($context === 'therapist')
                                        <a href="{{ route('therapist.ssas.show', $ssa) }}"
                                            class="inline-flex items-center justify-center w-9 h-9 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="View SSA"
                                            aria-label="View SSA {{ $ssa->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.ssas.show', $ssa) }}"
                                            class="inline-flex items-center justify-center w-9 h-9 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="View SSA"
                                            aria-label="View SSA {{ $ssa->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.ssas.edit', $ssa) }}"
                                            class="inline-flex items-center justify-center w-9 h-9 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            title="Edit SSA"
                                            aria-label="Edit SSA {{ $ssa->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                </path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                </path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <x-ui::empty-state title="No SSAs found."
            :description="$context === 'index' ? 'Create a new SSA to define services and scheduling for a student.' : null"
            :action-label="$context === 'index' ? 'Add SSA' : null"
            :action-href="$context === 'index' ? route('admin.ssas.create') : null" />
    @endif
</x-ui::card>

{{-- Hidden select for therapist assignment (used by admin-ssas-index.js) --}}
<select id="therapist_select_for_assignment" class="hidden">
    <option value="">Select a therapist</option>
    @foreach ($therapists ?? [] as $therapist)
        <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
    @endforeach
</select>
