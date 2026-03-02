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

    {{-- Filter Toolbar --}}
    <x-ui::card class="p-4 mb-6">
        <form id="leadsFiltersForm" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <x-input-label for="filter_search" value="Search" />
                <x-ui::input type="text" name="search" id="filter_search" placeholder="Search by name, email..."
                    class="mt-1 block w-full" />
            </div>
            <div class="min-w-[160px]">
                <x-input-label for="filter_status" value="Status" />
                <x-ui::select name="status" id="filter_status" class="mt-1" placeholder="All Statuses">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </x-ui::select>
            </div>
            <div class="min-w-[160px]">
                <x-input-label for="filter_source" value="Source" />
                <x-ui::select name="source" id="filter_source" class="mt-1" placeholder="All Sources">
                    <option value="">All Sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}">{{ $source->label() }}</option>
                    @endforeach
                </x-ui::select>
            </div>
            <div class="min-w-[160px]">
                <x-input-label for="filter_school_id" value="School" />
                <x-ui::select name="school_id" id="filter_school_id" class="mt-1" placeholder="All Schools">
                    <option value="">All Schools</option>
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}">{{ $school->display_name }}</option>
                    @endforeach
                </x-ui::select>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit" variant="secondary">Filter</x-ui::button>
                <a href="{{ route('admin.leads.create') }}">
                    <x-ui::button type="button">Create Lead</x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    {{-- DataTable --}}
    <x-ui::card class="overflow-x-auto">
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
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-leads-index.js'])
    </x-slot>
</x-admin.layouts.app>
