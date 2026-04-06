<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA Expirations & Pipeline Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Summary Metrics --}}
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-4">
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Upcoming Expirations</p>
            <p class="text-3xl font-semibold mt-1" id="metricUpcoming">0</p>
            <p class="text-xs text-foreground/60" id="metricUpcomingSubtext">next 30 days</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Expired (Active)</p>
            <p class="text-3xl font-semibold text-danger mt-1" id="metricExpired">0</p>
            <p class="text-xs text-foreground/60">need completion</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">Pending</p>
            <p class="text-3xl font-semibold text-warning mt-1" id="metricPending">0</p>
            <p class="text-xs text-foreground/60">need activation</p>
        </x-ui::card>
        <x-ui::card class="p-6">
            <p class="text-sm text-foreground/70">No Current SSA</p>
            <p class="text-3xl font-semibold mt-1" id="metricNoCurrent">0</p>
        </x-ui::card>
    </div>

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form id="expirationFiltersForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <x-input-label for="expiration_window_days" value="Expiration Window (Days)" />
                    <p class="mt-1 text-xs text-foreground/60" id="expiration_window_days_help">
                        Number of days ahead to check for expiring SSAs.
                    </p>
                    <x-ui::input type="number" id="expiration_window_days" name="expiration_window_days"
                        value="{{ $filters['expiration_window_days'] ?? 30 }}" class="mt-1 block w-full"
                        aria-describedby="expiration_window_days_help" />
                </div>
                <div>
                    <x-input-label for="school_ids" value="Schools/Families" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_ids_help">
                        Select one or more schools or families to filter.
                    </p>
                    <x-ui::select id="school_ids" name="school_ids[]" multiple searchable placeholder="All Schools/Families"
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
                    <x-input-label for="bucket" value="View" />
                    <p class="mt-1 text-xs text-foreground/60" id="bucket_help">
                        Filter by expiration status bucket.
                    </p>
                    <select id="bucket" name="bucket" aria-describedby="bucket_help"
                        class="mt-1 w-full px-3 py-2 border border-border rounded-md text-sm focus:ring-2 focus:ring-primary focus:outline-none">
                        <option value="upcoming" @selected(($filters['bucket'] ?? 'upcoming') === 'upcoming')>Upcoming</option>
                        <option value="expired" @selected(($filters['bucket'] ?? '') === 'expired')>Expired</option>
                        <option value="pending" @selected(($filters['bucket'] ?? '') === 'pending')>Pending</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit">Apply Filters</x-ui::button>
                <x-ui::button type="button" variant="secondary" id="resetFiltersBtn">
                    Reset
                </x-ui::button>
                <a href="{{ route('admin.reports.ssa.expirations.export', $filters) }}">
                    <x-ui::button type="button" variant="secondary">Export CSV</x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- Data Table --}}
    <x-ui::card class="p-6">
        <div class="overflow-x-auto">
            <table id="expirationTable" class="w-full display"
                data-datatable-url="{{ $datatableUrl }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">SSA ID</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Student</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">School/Family</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Therapist</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Service</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">End Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Days Until/Since</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground">Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-reports-ssa-expirations-index.js'])
    </x-slot>
</x-admin.layouts.app>
