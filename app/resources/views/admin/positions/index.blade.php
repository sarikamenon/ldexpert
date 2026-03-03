<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Positions" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Positions</p>
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

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="positionFiltersForm">
            <x-slot:filters>
                <x-ui::input type="text" name="search" class="w-48" placeholder="Search positions"
                    value="{{ $filters['search'] ?? '' }}" />

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true"
                    class="w-32">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.positions.export', $filters) }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle"
                    id="exportPositionsButton">
                    Export
                </a>
                <a href="{{ route('admin.positions.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Position
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        @if (isset($datatableUrl) || $positions->count() > 0)
            <div class="overflow-x-auto">
                <table id="positionsTable" class="w-full display" @if(isset($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!isset($datatableUrl))
                        @foreach ($positions as $position)
                            <tr>
                                <td class="font-medium">{{ $position->name }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($position->services as $service)
                                            <x-ui::badge variant="secondary">{{ $service->name }}</x-ui::badge>
                                        @empty
                                            <span class="text-sm text-foreground/50">No services</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <x-ui::badge :variant="$position->status === \App\Enums\PositionStatus::ACTIVE
                                        ? 'success'
                                        : 'secondary'">
                                        {{ $position->status->label() }}
                                    </x-ui::badge>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.positions.edit', $position) }}"
                                            class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors">
                                            Edit
                                        </a>
                                        <button type="button"
                                            class="toggle-position-status inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $position->status === \App\Enums\PositionStatus::ACTIVE ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                            data-position="{{ $position->id }}"
                                            data-status="{{ $position->status->value }}">
                                            {{ $position->status === \App\Enums\PositionStatus::ACTIVE ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-10">
                <p class="text-foreground/70 mb-4">No positions found.</p>
                <a href="{{ route('admin.positions.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Position
                </a>
            </div>
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-positions-index.js'])
    </x-slot>
</x-admin.layouts.app>
