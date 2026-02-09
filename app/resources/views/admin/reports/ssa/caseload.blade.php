<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA Caseload & Assignment Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Summary Metrics --}}
    @php
        $metricItems = [
            ['label' => 'Total Therapists', 'value' => $summary['total_therapists'] ?? 0, 'valueClass' => 'text-3xl'],
            ['label' => 'Total Active SSAs', 'value' => $summary['total_active_ssas'] ?? 0, 'valueClass' => 'text-3xl'],
            [
                'label' => 'Median Minutes/Week',
                'value' => number_format($summary['median_minutes_per_week'] ?? 0, 0),
                'valueClass' => 'text-3xl',
            ],
            [
                'label' => 'Unassigned SSAs',
                'value' => $unassignedSSAs->count(),
                'valueClass' => 'text-3xl text-warning',
            ],
        ];
    @endphp
    <x-ui::metric-grid :items="$metricItems" />

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.reports.ssa.caseload.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="school_ids" value="Schools" />
                    <p class="mt-1 text-xs text-foreground/60" id="school_ids_help">
                        Select one or more schools to filter.
                    </p>
                    <x-ui::select id="school_ids" name="school_ids[]" multiple searchable placeholder="All Schools"
                        class="mt-1" aria-describedby="school_ids_help">
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}" @selected(in_array($school->id, $filters['school_ids'] ?? []))>
                                {{ $school->display_name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div>
                    <x-input-label for="therapist_ids" value="Therapists" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_ids_help">
                        Select one or more therapists to filter.
                    </p>
                    <x-ui::select id="therapist_ids" name="therapist_ids[]" multiple searchable
                        placeholder="All Therapists" class="mt-1" aria-describedby="therapist_ids_help">
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}" @selected(in_array($therapist->id, $filters['therapist_ids'] ?? []))>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
                <div>
                    <x-input-label for="service_ids" value="Services" />
                    <p class="mt-1 text-xs text-foreground/60" id="service_ids_help">
                        Select one or more services to filter.
                    </p>
                    <x-ui::select id="service_ids" name="service_ids[]" multiple searchable placeholder="All Services"
                        class="mt-1" aria-describedby="service_ids_help">
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected(in_array($service->id, $filters['service_ids'] ?? []))>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit">Apply Filters</x-ui::button>
                <x-ui::button type="button" variant="secondary"
                    onclick="window.location.href='{{ route('admin.reports.ssa.caseload.index') }}'">
                    Reset
                </x-ui::button>
                <x-ui::button type="submit" variant="secondary"
                    formaction="{{ route('admin.reports.ssa.caseload.export') }}">
                    Export CSV
                </x-ui::button>
            </div>
        </form>
    </x-ui::card>

    {{-- Therapist Caseload Table --}}
    <x-ui::card class="p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Therapist Caseloads</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Therapist
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Schools
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Active SSAs
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Minutes/Week
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($therapistData as $data)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $data['therapist']->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @foreach ($data['schools'] as $school)
                                    <span
                                        class="inline-block bg-background border border-border rounded px-2 py-1 text-xs mr-1 mb-1">
                                        {{ $school->display_name }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $data['active_ssa_count'] }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ number_format($data['authorized_minutes_per_week'], 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-foreground/60">
                                No therapist caseloads found matching the filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui::card>

    {{-- Unassigned SSAs --}}
    @if ($unassignedSSAs->isNotEmpty())
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold mb-4">Unassigned SSAs</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead>
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Student
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                School
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Service
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                THO Minutes
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($unassignedSSAs as $ssa)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $ssa->student->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $ssa->student->studentProfile->school->display_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $ssa->primaryService->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ number_format($ssa->tho_minutes ?? 0) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('admin.ssas.show', $ssa) }}"
                                        class="text-primary hover:underline text-sm">View SSA</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @endif
</x-admin.layouts.app>
