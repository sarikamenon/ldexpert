<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Therapist Bills" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="therapistBillsFiltersForm">
            <x-slot:filters>
                <x-ui::select name="therapist_id" searchable placeholder="All Therapists" :inline="true" class="w-40">
                    <option value="">All Therapists</option>
                    @foreach ($therapists ?? [] as $therapist)
                        <option value="{{ $therapist->id }}" @selected((int) ($filters['therapist_id'] ?? 0) === $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true" class="w-36">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\TherapistBillStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                <x-ui::input type="text" name="bill_number" value="{{ $filters['bill_number'] ?? '' }}"
                    placeholder="Bill #" class="w-32" />
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.billing.therapist-bills.create') }}">
                    <x-ui::button>Create Bill</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="therapistBillsTable" class="w-full display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Therapist</th>
                        <th>Period</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapist-bills-index.js'])
    </x-slot>
</x-admin.layouts.app>
