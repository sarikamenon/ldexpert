<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Activity Logs" />

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="activityLogFiltersForm">
            <x-slot:filters>
                <x-ui::input type="text" name="search" placeholder="Search description"
                    value="{{ $filters['search'] ?? '' }}" class="w-48" />

                <x-ui::select name="user_id" searchable allow-clear placeholder="All Users" :inline="true" class="w-36">
                    <option value="">All Users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="action" searchable allow-clear placeholder="All Actions" :inline="true" class="w-36">
                    <option value="">All Actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>
                            {{ ucfirst(str_replace('_', ' ', $action)) }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="model_type" searchable allow-clear placeholder="All Models" :inline="true" class="w-36">
                    <option value="">All Models</option>
                    @foreach ($modelTypes as $modelType)
                        <option value="{{ $modelType }}" @selected(($filters['model_type'] ?? null) === $modelType)>
                            {{ $modelType }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                @if (!empty(array_filter($filters ?? [])))
                    <a href="{{ route('admin.activity-logs.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.activity-logs.export', $filters) }}">
                    <x-ui::button variant="secondary">Export</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <!-- Logs Table -->
        <div class="overflow-x-auto">
            <table id="activityLogsTable" class="w-full display"
                data-datatable-url="{{ $datatableUrl ?? route('admin.activity-logs.data') }}">
                <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>

        <div id="activityLogsStatus" class="sr-only" role="status" aria-live="polite"></div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-activity-logs-index.js'])
    </x-slot>
</x-admin.layouts.app>
