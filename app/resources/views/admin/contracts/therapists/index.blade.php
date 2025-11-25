<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Therapist Contracts" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Contracts</p>
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
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <form method="GET" class="flex flex-wrap gap-2">
                <x-text-input type="text" name="search" class="w-64" placeholder="Search by ID or Therapist"
                    value="{{ $filters['search'] ?? '' }}" />

                <div class="relative">
                    <select name="status"
                        class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Filter</button>
            </form>

            <a href="{{ route('admin.contracts.therapists.create') }}"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Add Contract
            </a>
        </div>

        <div class="overflow-x-auto">
            <table id="therapistContractsTable" class="w-full display">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Therapist</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Services</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contracts as $contract)
                        <tr>
                            <td>#{{ $contract->id }}</td>
                            <td>{{ $contract->therapist?->first_name }} {{ $contract->therapist?->last_name }}</td>
                            <td>{{ $contract->start_date?->format('M d, Y') }}</td>
                            <td>{{ $contract->end_date?->format('M d, Y') }}</td>
                            <td>{{ $contract->services->count() }}</td>
                            <td>
                                <x-ui::badge :variant="$contract->status === \App\Enums\ContractStatus::ACTIVE
                                    ? 'success'
                                    : 'danger'">
                                    {{ $contract->status->label() }}
                                </x-ui::badge>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.contracts.therapists.show', $contract) }}"
                                        class="inline-flex items-center px-3 py-1.5 border border-border rounded-md text-sm hover:bg-background/subtle">
                                        View
                                    </a>
                                    <a href="{{ route('admin.contracts.therapists.edit', $contract) }}"
                                        class="inline-flex items-center px-3 py-1.5 bg-primary text-white rounded-md text-sm hover:bg-primary/90">
                                        Edit
                                    </a>
                                    <button type="button"
                                        class="therapist-contract-status-toggle inline-flex items-center px-3 py-1.5 rounded-md text-sm {{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                        data-endpoint="{{ route('admin.contracts.therapists.status', $contract) }}"
                                        data-next-status="{{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? \App\Enums\ContractStatus::INACTIVE->value : \App\Enums\ContractStatus::ACTIVE->value }}">
                                        {{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $contracts->withQueryString()->links() }}
    </x-ui::card>

    @vite(['resources/js/pages/admin-contracts-therapists-index.js'])
</x-admin.layouts.app>
