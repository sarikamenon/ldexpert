<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Billing Schedules" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="billingScheduleFiltersForm">
            <x-slot:filters>
                <x-ui::select name="schedule_type" :searchable="false" placeholder="All Types" :inline="true" class="w-44">
                    <option value="">All Types</option>
                    @foreach ($scheduleTypes as $type)
                        <option value="{{ $type->value }}" @selected(($filters['schedule_type'] ?? null) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="billing_mode" :searchable="false" placeholder="All Modes" :inline="true" class="w-36">
                    <option value="">All Modes</option>
                    @foreach ($billingModes as $mode)
                        <option value="{{ $mode->value }}" @selected(($filters['billing_mode'] ?? null) === $mode->value)>
                            {{ $mode->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="is_active" :searchable="false" placeholder="All Statuses" :inline="true" class="w-36">
                    <option value="">All Statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? null) === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? null) === '0')>Inactive</option>
                </x-ui::select>
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.billing.schedules.create') }}">
                    <x-ui::button>Create Schedule</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="billingSchedulesTable" class="w-full display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead>
                    <tr>
                        <th>Entity</th>
                        <th>Type</th>
                        <th>Mode</th>
                        <th>Frequency</th>
                        <th>Next Run</th>
                        <th>Last Run</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-billing-schedules-index.js'])
    </x-slot>
</x-admin.layouts.app>
