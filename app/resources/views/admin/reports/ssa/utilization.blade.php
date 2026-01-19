<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA Utilization & Compliance Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Summary Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Authorized (THO)</p>
            <p class="text-3xl font-semibold mt-1">{{ number_format($summary['total_tho_minutes'] ?? 0) }}</p>
            <p class="text-xs text-foreground/60 mt-1">minutes</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Served</p>
            <p class="text-3xl font-semibold mt-1">{{ number_format($summary['total_served_minutes'] ?? 0) }}</p>
            <p class="text-xs text-foreground/60 mt-1">minutes</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Overall Utilization</p>
            <p class="text-3xl font-semibold mt-1">{{ number_format($summary['overall_utilization_percent'] ?? 0, 1) }}%</p>
        </x-ui::card>
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Under-served</p>
            <p class="text-3xl font-semibold mt-1 text-danger">{{ $summary['under_served_count'] ?? 0 }}</p>
            <p class="text-xs text-foreground/60 mt-1">SSAs</p>
        </x-ui::card>
    </div>

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.reports.ssa.utilization.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="start_date" value="Start Date *" />
                    <p class="mt-1 text-xs text-foreground/60" id="start_date_help">
                        Filter SSAs active within this date range.
                    </p>
                    <x-text-input type="date" id="start_date" name="start_date"
                        value="{{ $filters['start_date'] ?? '' }}"
                        class="mt-1 block w-full" aria-describedby="start_date_help" />
                </div>
                <div>
                    <x-input-label for="end_date" value="End Date *" />
                    <p class="mt-1 text-xs text-foreground/60" id="end_date_help">
                        Filter SSAs active within this date range.
                    </p>
                    <x-text-input type="date" id="end_date" name="end_date"
                        value="{{ $filters['end_date'] ?? '' }}"
                        class="mt-1 block w-full" aria-describedby="end_date_help" />
                </div>
                <div>
                    <x-input-label for="school_ids" value="Schools" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_ids_help">
                        Select one or more schools to filter.
                    </p>
                    <select id="school_ids" name="school_ids[]" multiple
                        class="mt-1 block w-full border border-border rounded-lg pl-3 pr-10 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                        aria-describedby="school_ids_help">
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}"
                                @selected(in_array($school->id, $filters['school_ids'] ?? []))>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="therapist_ids" value="Therapists" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_ids_help">
                        Select one or more therapists to filter.
                    </p>
                    <select id="therapist_ids" name="therapist_ids[]" multiple
                        class="mt-1 block w-full border border-border rounded-lg pl-3 pr-10 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                        aria-describedby="therapist_ids_help">
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}"
                                @selected(in_array($therapist->id, $filters['therapist_ids'] ?? []))>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="service_ids" value="Services" />
                    <p class="mt-1 text-xs text-foreground/60" id="service_ids_help">
                        Select one or more services to filter.
                    </p>
                    <select id="service_ids" name="service_ids[]" multiple
                        class="mt-1 block w-full border border-border rounded-lg pl-3 pr-10 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                        aria-describedby="service_ids_help">
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}"
                                @selected(in_array($service->id, $filters['service_ids'] ?? []))>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-primary-button type="submit">Apply Filters</x-primary-button>
                <x-secondary-button type="button"
                    onclick="window.location.href='{{ route('admin.reports.ssa.utilization.index') }}'">
                    Reset
                </x-secondary-button>
                <x-secondary-button type="submit" formaction="{{ route('admin.reports.ssa.utilization.export') }}">
                    Export CSV
                </x-secondary-button>
            </div>
        </form>
    </x-ui::card>

    {{-- Data Table --}}
    <x-ui::card class="p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            SSA ID
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Student
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            School
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Therapist
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Service
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            THO Minutes
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Served Minutes
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Utilization %
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($ssas as $ssa)
                        @php
                            $tho = $ssa->tho_minutes ?? 0;
                            $served = $ssa->served_minutes ?? 0;
                            $utilization = $tho > 0 ? round(($served / $tho) * 100, 2) : 0;
                            $isNearEnd = $ssa->end_date->diffInDays(now()) <= 30;
                            $badgeColor = $utilization < 80 && $isNearEnd ? 'danger' : ($utilization > 120 ? 'warning' : 'success');
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="{{ route('admin.ssas.show', $ssa) }}"
                                    class="text-primary hover:underline">#{{ $ssa->id }}</a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $ssa->student->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $ssa->student->studentProfile->school->display_name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $ssa->assignedTherapist->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $ssa->primaryService->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ number_format($tho) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ number_format($served) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-ui::badge variant="{{ $badgeColor }}">{{ $utilization }}%</x-ui::badge>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-ui::badge>{{ $ssa->status->label() }}</x-ui::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-foreground/60">
                                No SSAs found matching the filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($ssas->hasPages())
            <div class="mt-4">
                {{ $ssas->links() }}
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>
