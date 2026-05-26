@props([
    'ssas',
    'filters' => [],
    'statuses' => [],
    'students' => [],
    'therapists' => [],
    'services' => [],
    'showMetrics' => false,
    'metrics' => null,
    /**
     * When set, table uses server-side DataTables and loads via AJAX from this URL.
     */
    'datatableUrl' => null,
    // context: 'index', 'detail', or 'therapist'
    'context' => 'index',
    'schoolId' => null,
    'therapistId' => null,
    'studentId' => null,
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
    @if ($context === 'detail' && $studentId)
        <div class="space-y-2">
        <div class="flex flex-wrap items-center gap-3 justify-between">
            <form method="GET" id="ssaFiltersForm" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="ssas">
                <input type="hidden" name="student_id" value="{{ $studentId }}">

                <div class="w-56 shrink-0">
                    <x-ui::input type="search" name="search" class="h-10" placeholder="Search SSAs"
                        value="{{ $filters['search'] ?? '' }}" />
                </div>

                <x-ui::select name="statuses[]" multiple :searchable="false" placeholder="Status: All" :inline="true" data-default-value="pending,active" class="min-w-[14rem]">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(in_array($status->value, $filters['statuses'] ?? ['pending', 'active'], true))>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="therapist_id" searchable placeholder="Therapist: All" :inline="true" class="min-w-[13rem]">
                    <option value="">Therapist: All</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}" @selected(($filters['therapist_id'] ?? null) == $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="service_id" searchable placeholder="Service: All" :inline="true" class="min-w-[13rem]">
                    <option value="">Service: All</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(($filters['service_id'] ?? null) == $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::button type="submit" size="lg">Filter</x-ui::button>
            </form>

            <div class="flex items-center gap-2">
                <div class="filter-divider hidden lg:block"></div>
                <a href="{{ route('admin.ssas.export', $filters) }}">
                    <x-ui::button variant="secondary">
                        <x-ui::icon name="download" class="w-4 h-4 mr-1.5" />
                        Export
                    </x-ui::button>
                </a>
                <a href="{{ route('admin.ssas.create') }}">
                    <x-ui::button>
                        <x-ui::icon name="plus" class="w-4 h-4 mr-1.5" />
                        Add SSA
                    </x-ui::button>
                </a>
            </div>
        </div>

        <div
            id="ssaFiltersSummary"
            class="hidden items-center gap-2 text-sm text-foreground-muted"
        >
            <span data-filter-count>0 filters applied</span>
            <span class="text-border">|</span>
            <button
                type="button"
                class="text-primary hover:underline font-medium"
                id="ssaFiltersClearAll"
            >
                Clear all
            </button>
        </div>
        </div>
    @else
        <x-ui::filter-toolbar formId="ssaFiltersForm">
            <x-slot:filters>
                @if ($schoolId)
                    <input type="hidden" name="school_id" value="{{ $schoolId }}">
                @endif
                @if ($therapistId)
                    <input type="hidden" name="therapist_id" value="{{ $therapistId }}">
                @endif

                <x-ui::input type="text" name="search" class="w-56" placeholder="Search SSAs"
                    value="{{ $filters['search'] ?? '' }}" />

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true" data-default-value="active">
                    <option value="all">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? 'active') === $status->value)>
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
                    <a href="{{ route('admin.ssas.export', $filters) }}">
                        <x-ui::button variant="secondary">
                            Export
                        </x-ui::button>
                    </a>
                    @if ($context === 'index')
                        <a href="{{ route('admin.ssas.create') }}">
                            <x-ui::button>
                                Add SSA
                            </x-ui::button>
                        </a>
                    @endif
                @endif
            </x-slot:actions>
        </x-ui::filter-toolbar>
    @endif

    @if (isset($datatableUrl) || $ssas->count() > 0)
        <div class="overflow-x-auto">
            <table id="ssasTable" class="w-full display" @if(isset($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student & Service</th>
                        <th>Therapist</th>
                        <th style="min-width: 180px;">Date Range</th>
                        <th>Session Details</th>
                        <th>Hours & Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!isset($datatableUrl))
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
                                        @if ($ssa->student?->studentProfile?->school?->is_private_student)
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-foreground/60 font-medium">Served:</span>
                                                <span class="text-sm text-foreground font-medium">{{ number_format($ssa->served_hours, 2) }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-foreground/60 font-medium">Scheduled:</span>
                                                <span class="text-sm text-foreground">{{ number_format($ssa->scheduled_hours, 2) }}</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-foreground/60 font-medium">THO:</span>
                                                <span class="text-sm text-foreground font-medium">{{ number_format($ssa->tho_hours, 2) }}</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-foreground/60 font-medium">Served:</span>
                                                <span class="text-sm text-foreground">{{ number_format($ssa->served_hours, 2) }}</span>
                                            </div>
                                        @endif
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
                                <div class="flex items-center gap-1">
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
                                        @if (! $ssa->assignedTherapist)
                                            <button type="button"
                                                class="assign-therapist-btn inline-flex items-center justify-center w-9 h-9 rounded bg-success text-success-foreground hover:bg-success/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                title="Assign Therapist"
                                                aria-label="Assign Therapist to SSA {{ $ssa->id }}"
                                                data-ssa-id="{{ $ssa->id }}"
                                                data-ssa-name="{{ $ssa->student?->name ?? 'SSA #'.$ssa->id }}"
                                                data-ssa-status="{{ $ssa->status->label() }}"
                                                data-service-name="{{ $ssa->primaryService?->name ?? '—' }}"
                                                data-service-ids="{{ json_encode([$ssa->primary_service_id]) }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                            </button>
                                        @endif
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
        <x-ui::empty-state title="No SSAs found."
            :description="$context === 'index' ? 'Create a new SSA to define services and scheduling for a student.' : null"
            :action-label="$context === 'index' ? 'Add SSA' : null"
            :action-href="$context === 'index' ? route('admin.ssas.create') : null" />
    @endif
</x-ui::card>

{{-- Endpoint URL for therapist assignment AJAX (used by admin-ssas-index.js) --}}
<script type="application/json" id="therapists-for-service-url">
    @json(route('admin.ssas.therapists-for-service'))
</script>

<x-ui::ssa-assign-modal />
<x-ui::ssa-unassign-modal />
