<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA Caseload & Assignment Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Summary Metrics --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Total Therapists</p>
            <p class="text-3xl font-semibold mt-1" id="metricTotalTherapists">0</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Total Active SSAs</p>
            <p class="text-3xl font-semibold mt-1" id="metricTotalActiveSSAs">0</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Median Minutes/Week</p>
            <p class="text-3xl font-semibold mt-1" id="metricMedianMinutes">0</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Unassigned SSAs</p>
            <p class="text-3xl font-semibold text-warning mt-1" id="metricUnassignedSSAs">0</p>
        </x-ui::card>
    </div>

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form id="caseloadFiltersForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="school_ids" value="Schools" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_ids_help">
                        Select one or more schools to filter.
                    </p>
                    <x-ui::select id="school_ids" name="school_ids[]" multiple searchable placeholder="All Schools"
                        class="mt-1" aria-describedby="school_ids_help">
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected(in_array($school->id, $filters['school_ids'] ?? []))>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div>
                    <x-input-label for="therapist_ids" value="Therapists" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_ids_help">
                        Select one or more therapists to filter.
                    </p>
                    <x-ui::select id="therapist_ids" name="therapist_ids[]" multiple searchable
                        placeholder="All Therapists" class="mt-1" aria-describedby="therapist_ids_help">
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}" @selected(in_array($therapist->id, $filters['therapist_ids'] ?? []))>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div>
                    <x-input-label for="service_ids" value="Services" />
                    <p class="mt-1 text-xs text-foreground/60" id="service_ids_help">
                        Select one or more services to filter.
                    </p>
                    <x-ui::select id="service_ids" name="service_ids[]" multiple searchable placeholder="All Services"
                        class="mt-1" aria-describedby="service_ids_help">
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected(in_array($service->id, $filters['service_ids'] ?? []))>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit">Apply Filters</x-ui::button>
                <x-ui::button type="button" variant="secondary" id="resetFiltersBtn">
                    Reset
                </x-ui::button>
                <a href="{{ route('admin.reports.ssa.caseload.export', $filters) }}">
                    <x-ui::button type="button" variant="secondary">Export CSV</x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Therapist Caseload Table --}}
    <x-ui::card class="p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Therapist Caseloads</h3>
        <div class="overflow-x-auto">
            <table id="caseloadTherapistTable" class="w-full display"
                data-datatable-url="{{ $therapistDatatableUrl }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Therapist</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Schools</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Active SSAs</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Minutes/Week</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    {{-- Unassigned SSAs --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Unassigned SSAs</h3>
        <div class="overflow-x-auto">
            <table id="caseloadUnassignedTable" class="w-full display"
                data-datatable-url="{{ $unassignedDatatableUrl }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Student</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">School</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Service</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">THO Hours</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-reports-ssa-caseload-index.js'])
    </x-slot>
</x-admin.layouts.app>
