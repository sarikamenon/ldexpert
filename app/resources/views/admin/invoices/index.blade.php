<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Invoices" />

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-4">{{ session('success') }}</x-ui::alert>
    @endif

    @if (session('error'))
        <x-ui::alert variant="danger" class="mb-4">{{ session('error') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="invoiceFiltersForm">
            <x-slot:filters>
                <x-ui::select name="school_id" searchable placeholder="All Schools" :inline="true" class="w-40">
                    <option value="">All Schools</option>
                    @foreach ($schools ?? [] as $school)
                        <option value="{{ $school->id }}" @selected((int) ($filters['school_id'] ?? 0) === $school->id)>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true" class="w-36">
                    <option value="">All Statuses</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                <x-ui::input type="text" name="invoice_number" value="{{ $filters['invoice_number'] ?? '' }}"
                    placeholder="Invoice #" class="w-32" />
            </x-slot:filters>

            <x-slot:actions>
                <a href="{{ route('admin.invoices.create') }}">
                    <x-ui::button>Create Invoice</x-ui::button>
                </a>
            </x-slot:actions>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="invoicesTable" class="w-full display"
                @if (!empty($datatableUrl)) data-datatable-url="{{ $datatableUrl }}" @endif>
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>School</th>
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
        @vite(['resources/js/pages/admin-invoices-index.js'])
    </x-slot>
</x-admin.layouts.app>
