@props([
    'contracts',
    'filters' => [],
    'statuses' => [],
    'schools' => [],
    'showMetrics' => false,
    'metrics' => null,
    'context' => 'index', // 'index' or 'detail'
])

@if ($showMetrics && $metrics)
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
@endif

<x-ui::card class="p-6 space-y-4">
    <x-ui::filter-toolbar formId="schoolContractsFiltersForm">
        <x-slot:filters>
            @if($context !== 'detail' && count($schools) > 0)
                @php
                    $selectedSchoolIds = collect($filters['school_ids'] ?? [])->map(fn($id) => (int) $id)->toArray();
                @endphp
                <x-ui::select name="school_ids[]" multiple searchable placeholder="Select Schools" :inline="true">
                    @foreach ($schools as $school)
                        <option value="{{ $school->id }}" @selected(in_array($school->id, $selectedSchoolIds, true))>
                            {{ $school->display_name }}
                        </option>
                    @endforeach
                </x-ui::select>
            @endif

            <x-ui::select name="status" :searchable="false" placeholder="All Statuses" :inline="true">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </x-ui::select>

            @if($context === 'detail')
                <input type="hidden" name="tab" value="contracts">
            @endif
        </x-slot:filters>

        <x-slot:actions>
            <a href="{{ route('admin.contracts.schools.create') }}"
                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Add Contract
            </a>
        </x-slot:actions>
    </x-ui::filter-toolbar>

    <div class="overflow-x-auto">
        <table id="schoolContractsTable" class="w-full display">
            <thead>
                <tr>
                    <th>ID</th>
                    @if($context !== 'detail')
                    <th>School</th>
                    @endif
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
                        @if($context !== 'detail')
                        <td>{{ $contract->school?->display_name ?? '—' }}</td>
                        @endif
                        <td>{{ $contract->start_date?->format('M d, Y') }}</td>
                        <td>{{ $contract->end_date?->format('M d, Y') }}</td>
                        <td>{{ $contract->services->count() }}</td>
                        <td>
                            <x-ui::badge :variant="$contract->status === \App\Enums\ContractStatus::ACTIVE ? 'success' : 'danger'">
                                {{ $contract->status->label() }}
                            </x-ui::badge>
                        </td>
                        <td>
                            <div class="flex space-x-1">
                                <a href="{{ route('admin.contracts.schools.show', $contract) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors"
                                    title="View Contract">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </a>
                                <a href="{{ route('admin.contracts.schools.edit', $contract) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors"
                                    title="Edit Contract">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </a>
                                <button type="button"
                                    class="contract-status-toggle inline-flex items-center justify-center w-8 h-8 rounded transition-colors {{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                    data-endpoint="{{ route('admin.contracts.schools.status', $contract) }}"
                                    data-next-status="{{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? \App\Enums\ContractStatus::INACTIVE->value : \App\Enums\ContractStatus::ACTIVE->value }}"
                                    title="{{ $contract->status === \App\Enums\ContractStatus::ACTIVE ? 'Deactivate Contract' : 'Activate Contract' }}">
                                    @if ($contract->status === \App\Enums\ContractStatus::ACTIVE)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                    @endif
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

