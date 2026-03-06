<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Service Aliases" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-{{ 1 + count($sources) }} gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Aliases</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>
        @foreach ($sources as $source)
            <x-ui::card class="p-4">
                <p class="text-sm text-foreground/70">{{ $source->value }}</p>
                <p class="text-3xl font-semibold mt-1">{{ $metrics[strtolower($source->value)] ?? 0 }}</p>
            </x-ui::card>
        @endforeach
    </div>

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="serviceAliasFiltersForm">
            <x-slot:filters>
                <x-ui::input type="text" name="search" class="w-48" placeholder="Search aliases"
                    value="" />

                <x-ui::select name="source" :searchable="false" placeholder="All Sources" :inline="true"
                    class="w-32">
                    <option value="">All Sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->value }}">
                            {{ $source->value }}
                        </option>
                    @endforeach
                </x-ui::select>
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.service-aliases.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Alias
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="serviceAliasesTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}">
                <thead>
                    <tr>
                        <th class="w-24">Source</th>
                        <th>External Name</th>
                        <th>System Service</th>
                        <th class="w-32">Created</th>
                        <th class="w-24">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-service-aliases-index.js'])
    </x-slot>
</x-admin.layouts.app>
