<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Activity Logs" />

    <x-ui::card class="p-6 space-y-4">
        <!-- Filters -->
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-end justify-between">
            <form method="GET" class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3"
                id="activityLogFiltersForm">
                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">Search</label>
                    <x-text-input type="text" name="search" placeholder="Search description"
                        value="{{ $filters['search'] ?? '' }}" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">User</label>
                    <x-ui::select name="user_id" searchable allow-clear placeholder="All Users">
                        <option value="">All Users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(($filters['user_id'] ?? null) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">Action</label>
                    <x-ui::select name="action" searchable allow-clear placeholder="All Actions">
                        <option value="">All Actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>
                                {{ ucfirst(str_replace('_', ' ', $action)) }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">Model Type</label>
                    <x-ui::select name="model_type" searchable allow-clear placeholder="All Models">
                        <option value="">All Models</option>
                        @foreach ($modelTypes as $modelType)
                            <option value="{{ $modelType }}" @selected(($filters['model_type'] ?? null) === $modelType)>
                                {{ $modelType }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">From Date</label>
                    <x-text-input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-foreground/70 mb-1">To Date</label>
                    <x-text-input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" />
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Filter
                    </button>
                    <a href="{{ route('admin.activity-logs.index') }}"
                        class="px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                        Clear
                    </a>
                </div>
            </form>

            <div class="flex gap-2">
                <a href="{{ route('admin.activity-logs.export', $filters) }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                    Export
                </a>
            </div>
        </div>

        <!-- Logs Table -->
        @if ($logs->count() > 0)
            <div class="overflow-x-auto">
                <table id="activityLogsTable" class="w-full display">
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
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>{{ $log->user?->name ?? 'System' }}</td>
                                <td>
                                    <x-ui::badge :variant="$log->action_variant"
                                        class="inline-flex items-center gap-1 text-xs font-medium capitalize">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                        {{ $log->action_label }}
                                    </x-ui::badge>
                                </td>
                                <td>{{ class_basename($log->model_type) }}</td>
                                <td class="max-w-md">
                                    <div class="truncate" title="{{ $log->description }}">
                                        {{ $log->description }}
                                    </div>
                                    @if ($log->formatted_changes)
                                        <div class="text-xs text-foreground/60 mt-1">
                                            {{ $log->formatted_changes }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $log->ip_address }}</td>
                                <td>
                                    <span title="{{ $log->created_at->format('Y-m-d H:i:s') }}">
                                        {{ $log->created_at->diffForHumans() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @else
            <div class="text-center py-10">
                <p class="text-foreground/70">No activity logs found.</p>
            </div>
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-activity-logs-index.js'])
    </x-slot>
</x-admin.layouts.app>
