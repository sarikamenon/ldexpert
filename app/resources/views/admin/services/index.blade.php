<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Services" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Services</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Active</p>
            <p class="text-3xl font-semibold mt-1 text-success">{{ $metrics['active'] ?? 0 }}</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Inactive</p>
            <p class="text-3xl font-semibold mt-1 text-danger">{{ $metrics['inactive'] ?? 0 }}</p>
        </x-ui::card>
    </div>

    <x-ui::card class="p-6 space-y-6">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <form method="GET" class="flex flex-wrap gap-3" id="serviceFiltersForm">
                <x-text-input type="text" name="search" class="w-56" placeholder="Search services"
                    value="{{ $filters['search'] ?? '' }}" />

                <select name="status"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <select name="is_frequency_service"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">Frequency?</option>
                    <option value="1" @selected(($filters['is_frequency_service'] ?? null) === '1')>Yes</option>
                    <option value="0" @selected(($filters['is_frequency_service'] ?? null) === '0')>No</option>
                </select>

                <select name="is_direct_service"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">Direct?</option>
                    <option value="1" @selected(($filters['is_direct_service'] ?? null) === '1')>Yes</option>
                    <option value="0" @selected(($filters['is_direct_service'] ?? null) === '0')>No</option>
                </select>

                <select name="is_group_service"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">Group?</option>
                    <option value="1" @selected(($filters['is_group_service'] ?? null) === '1')>Yes</option>
                    <option value="0" @selected(($filters['is_group_service'] ?? null) === '0')>No</option>
                </select>

                <select name="is_billable"
                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="">Billable?</option>
                    <option value="1" @selected(($filters['is_billable'] ?? null) === '1')>Yes</option>
                    <option value="0" @selected(($filters['is_billable'] ?? null) === '0')>No</option>
                </select>

                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Filter</button>
            </form>

            <a href="{{ route('admin.services.create') }}"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Add Service
            </a>
        </div>

        @if ($services->count() > 0)
            <div class="overflow-x-auto">
                <table id="servicesTable" class="w-full display">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Frequency</th>
                            <th>Flags</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr>
                                <td class="font-medium">
                                    <div>{{ $service->name }}</div>
                                    <p class="text-sm text-foreground/70">
                                        {{ \Illuminate\Support\Str::limit($service->description, 60) }}</p>
                                </td>
                                <td>
                                    <span class="inline-flex items-center gap-1">
                                        <span
                                            class="w-2 h-2 rounded-full {{ $service->is_frequency_service ? 'bg-success' : 'bg-border' }}"></span>
                                        {{ $service->is_frequency_service ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1 text-xs">
                                        <span class="inline-flex items-center gap-1">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $service->is_direct_service ? 'bg-success' : 'bg-border' }}"></span>
                                            Direct
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $service->is_group_service ? 'bg-primary' : 'bg-border' }}"></span>
                                            Group
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $service->is_billable ? 'bg-success' : 'bg-border' }}"></span>
                                            Billable
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($service->min_duration_minutes || $service->max_duration_minutes)
                                        <span class="text-sm">
                                            {{ $service->min_duration_minutes ?? '—' }} -
                                            {{ $service->max_duration_minutes ?? '—' }} mins
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <x-ui::badge :variant="$service->status === \App\Enums\ServiceStatus::ACTIVE
                                        ? 'success'
                                        : 'secondary'">
                                        {{ $service->status->label() }}
                                    </x-ui::badge>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.services.edit', $service) }}"
                                            class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors">
                                            Edit
                                        </a>
                                        <button type="button"
                                            class="toggle-service-status inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $service->status === \App\Enums\ServiceStatus::ACTIVE ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                            data-service="{{ $service->id }}"
                                            data-status="{{ $service->status->value }}">
                                            {{ $service->status === \App\Enums\ServiceStatus::ACTIVE ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-10">
                <p class="text-foreground/70 mb-4">No services found.</p>
                <a href="{{ route('admin.services.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Service
                </a>
            </div>
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-services-index.js'])
    </x-slot>
</x-admin.layouts.app>
