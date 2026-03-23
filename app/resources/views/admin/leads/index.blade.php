<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Leads" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Leads</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Active Pipeline</p>
            <p class="text-3xl font-semibold mt-1 text-info">{{ $metrics['active_pipeline'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Overdue Follow-ups</p>
            <p class="text-3xl font-semibold mt-1 {{ ($metrics['overdue_follow_ups'] ?? 0) > 0 ? 'text-danger' : '' }}">
                {{ $metrics['overdue_follow_ups'] ?? 0 }}
            </p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">This Month</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['this_month'] ?? 0 }}</p>
        </x-ui::card>
    </div>

    {{-- Filter Toolbar + DataTable --}}
    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="leadsFiltersForm">
            <x-slot:filters>
                <x-ui::input type="text" name="search" id="filter_search" class="w-64"
                    placeholder="Search by name, email..." />

                <x-ui::select name="status" id="filter_status" :searchable="false" placeholder="All Statuses" :inline="true">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="source" id="filter_source" :searchable="false" placeholder="All Sources" :inline="true">
                    <option value="">All Sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}">{{ $source->label() }}</option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="school_id" id="filter_school_id" :searchable="false" placeholder="All Schools" :inline="true">
                    <option value="">All Schools</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}">{{ $school->display_name }}</option>
                    @endforeach
                </x-ui::select>
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.leads.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Create Lead
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="leadsTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>School</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Follow-up</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-leads-index.js'])
    </x-slot>
</x-admin.layouts.app>
