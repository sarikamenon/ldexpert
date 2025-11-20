<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>
    <x-page-title title="Therapists" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Therapists</p>
            <p class="text-3xl font-semibold mt-1">{{ $metrics['total'] ?? 0 }}</p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Active</p>
            <p class="text-3xl font-semibold mt-1 text-success">{{ $metrics['active'] ?? 0 }}</p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Deactivated</p>
            <p class="text-3xl font-semibold mt-1 text-danger">{{ $metrics['inactive'] ?? 0 }}</p>
        </x-ui::card>
    </div>

    <x-ui::card class="p-6 space-y-4">
        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div class="flex flex-wrap gap-3">
                <form method="GET" class="flex gap-2" id="therapistsFiltersForm">
                    <x-text-input type="text" name="search" class="w-64" placeholder="Search therapists"
                        value="{{ $filters['search'] ?? '' }}" />

                    <div class="relative">
                        <select name="status"
                            class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="">All Statuses</option>
                            @foreach (\App\Enums\UserStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                                    {{ ucfirst($status->value) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="relative">
                        <select name="position"
                            class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="">All Positions</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->value }}" @selected(($filters['position'] ?? null) === $position->value)>
                                    {{ $position->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">Filter</button>
                </form>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.therapists.export', $filters) }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle"
                    id="exportTherapistsButton">
                    Export
                </a>
                <a href="{{ route('admin.therapists.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Therapist
                </a>
            </div>
        </div>

        @if ($therapists->count() > 0)
            <div class="overflow-x-auto">
                <table id="therapistsTable" class="w-full display">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Manager</th>
                            <th>Position</th>
                            <th>Max Weekly Hours</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($therapists as $therapist)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.therapists.show', $therapist) }}"
                                        class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors"
                                        title="View Therapist">
                                        {{ $therapist->id }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.therapists.show', $therapist) }}"
                                        class="text-primary hover:underline font-medium">
                                        {{ $therapist->name }}
                                    </a>
                                </td>
                                <td>{{ $therapist->email }}</td>
                                <td>{{ $therapist->therapistProfile?->manager?->name ?? '—' }}</td>
                                <td>{{ $therapist->therapistProfile?->position?->value ?? '—' }}</td>
                                <td>{{ $therapist->therapistProfile?->max_weekly_hours ?? '—' }}</td>
                                <td>
                                    <x-ui::badge :variant="$therapist->status?->value === 'active' ? 'success' : 'secondary'">
                                        {{ ucfirst($therapist->status?->value ?? 'inactive') }}
                                    </x-ui::badge>
                                </td>
                                <td>
                                    <div class="flex space-x-1">
                                        <a href="{{ route('admin.therapists.show', $therapist) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors"
                                            title="View Therapist" dusk="view-therapist-{{ $therapist->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.therapists.edit', $therapist) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors"
                                            title="Edit Therapist" dusk="edit-therapist-{{ $therapist->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                </path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                </path>
                                            </svg>
                                        </a>

                                        @php
                                            $isActive = ($therapist->status?->value ?? 'inactive') === 'active';
                                        @endphp
                                        <button type="button" data-therapist="{{ $therapist->id }}"
                                            data-status="{{ $therapist->status?->value ?? 'inactive' }}"
                                            dusk="status-toggle-{{ $therapist->id }}"
                                            class="toggle-status-button inline-flex items-center justify-center w-8 h-8 rounded transition-colors {{ $isActive ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90' }}"
                                            title="{{ $isActive ? 'Deactivate Therapist' : 'Activate Therapist' }}">
                                            @if ($isActive)
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6" y2="18">
                                                    </line>
                                                    <line x1="6" y1="6" x2="18" y2="18">
                                                    </line>
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
        @else
            <div class="text-center py-10">
                <p class="text-foreground/70 mb-4">No therapists found.</p>
                <a href="{{ route('admin.therapists.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Add Therapist
                </a>
            </div>
        @endif
    </x-ui::card>

    <form method="POST" id="therapistStatusForm" class="hidden">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" id="statusInput">
        <input type="hidden" name="reason" id="statusReasonInput">
    </form>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapists-index.js'])
    </x-slot>
</x-admin.layouts.app>
