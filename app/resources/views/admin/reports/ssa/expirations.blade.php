<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="SSA Expirations & Pipeline Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Summary Metrics --}}
    @php
        $metricItems = [
            [
                'label' => 'Upcoming Expirations',
                'value' => $summary['upcoming_count'] ?? 0,
                'valueClass' => 'text-3xl',
                'subtext' => 'next ' . ($summary['expiration_window_days'] ?? 30) . ' days',
            ],
            [
                'label' => 'Expired (Active)',
                'value' => $summary['expired_count'] ?? 0,
                'valueClass' => 'text-3xl text-danger',
                'subtext' => 'need completion',
            ],
            [
                'label' => 'Pending',
                'value' => $summary['pending_count'] ?? 0,
                'valueClass' => 'text-3xl text-warning',
                'subtext' => 'need activation',
            ],
            [
                'label' => 'No Current SSA',
                'value' => $summary['no_current_count'] ?? 0,
                'valueClass' => 'text-3xl',
            ],
        ];
    @endphp
    <x-ui::metric-grid :items="$metricItems" />

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form method="GET" action="{{ route('admin.reports.ssa.expirations.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <x-input-label for="expiration_window_days" value="Expiration Window (Days)" />
                    <p class="mt-1 text-xs text-foreground/60" id="expiration_window_days_help">
                        Number of days ahead to check for expiring SSAs.
                    </p>
                    <x-ui::input type="number" id="expiration_window_days" name="expiration_window_days"
                        value="{{ $filters['expiration_window_days'] ?? 30 }}" class="mt-1 block w-full"
                        aria-describedby="expiration_window_days_help" />
                </div>
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
                    <x-input-label for="bucket" value="View" />
                    <p class="mt-1 text-xs text-foreground/60" id="bucket_help">
                        Filter by expiration status bucket.
                    </p>
                    <x-ui::select id="bucket" name="bucket" searchable class="mt-1"
                        aria-describedby="bucket_help">
                        <option value="">All</option>
                        <option value="upcoming" @selected(($filters['bucket'] ?? '') === 'upcoming')>Upcoming</option>
                        <option value="expired" @selected(($filters['bucket'] ?? '') === 'expired')>Expired</option>
                        <option value="pending" @selected(($filters['bucket'] ?? '') === 'pending')>Pending</option>
                        </select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit">Apply Filters</x-ui::button>
                <x-ui::button type="button" variant="secondary"
                    onclick="window.location.href='{{ route('admin.reports.ssa.expirations.index') }}'">
                    Reset
                </x-ui::button>
                <x-ui::button type="submit" variant="secondary"
                    formaction="{{ route('admin.reports.ssa.expirations.export') }}">
                    Export CSV
                </x-ui::button>
            </div>
        </form>
    </x-ui::card>

    {{-- Tabs --}}
    <div class="mb-4">
        <div class="border-b border-border">
            <nav class="-mb-px flex space-x-8">
                <a href="{{ route('admin.reports.ssa.expirations.index', array_merge($filters, ['bucket' => 'upcoming'])) }}"
                    class="@if (($filters['bucket'] ?? 'upcoming') === 'upcoming') border-primary text-primary @else border-transparent text-foreground/60 hover:text-foreground hover:border-foreground/30 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Upcoming Expirations ({{ $upcoming->count() }})
                </a>
                <a href="{{ route('admin.reports.ssa.expirations.index', array_merge($filters, ['bucket' => 'expired'])) }}"
                    class="@if (($filters['bucket'] ?? '') === 'expired') border-primary text-primary @else border-transparent text-foreground/60 hover:text-foreground hover:border-foreground/30 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Expired ({{ $expired->count() }})
                </a>
                <a href="{{ route('admin.reports.ssa.expirations.index', array_merge($filters, ['bucket' => 'pending'])) }}"
                    class="@if (($filters['bucket'] ?? '') === 'pending') border-primary text-primary @else border-transparent text-foreground/60 hover:text-foreground hover:border-foreground/30 @endif whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Pending ({{ $pending->count() }})
                </a>
            </nav>
        </div>
    </div>

    {{-- Data Table --}}
    @php
        $activeBucket = $filters['bucket'] ?? 'upcoming';
        $displayData = match ($activeBucket) {
            'expired' => $expired,
            'pending' => $pending,
            default => $upcoming,
        };
    @endphp

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
                            End Date
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Days Until/Since
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($displayData as $ssa)
                        @php
                            $endDate = \Carbon\Carbon::parse($ssa->end_date ?? now());
                            $daysDiff = now()->diffInDays($endDate, false);
                        @endphp
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->id))
                                    <a href="{{ route('admin.ssas.show', $ssa) }}"
                                        class="text-primary hover:underline">#{{ $ssa->id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->student))
                                    @if (is_object($ssa->student) && isset($ssa->student->name))
                                        {{ $ssa->student->name }}
                                    @else
                                        {{ $ssa->student }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->student) &&
                                        is_object($ssa->student) &&
                                        $ssa->student->studentProfile &&
                                        $ssa->student->studentProfile->school)
                                    {{ $ssa->student->studentProfile->school->display_name }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->assignedTherapist))
                                    {{ $ssa->assignedTherapist->name ?? 'Unassigned' }}
                                @else
                                    Unassigned
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->primaryService))
                                    {{ $ssa->primaryService->name ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->end_date) && $ssa->end_date)
                                    {{ $ssa->end_date->format('Y-m-d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($daysDiff > 0)
                                    <span class="text-warning">{{ $daysDiff }} days</span>
                                @else
                                    <span class="text-danger">{{ abs($daysDiff) }} days ago</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if (isset($ssa->status))
                                    <x-ui::badge>{{ $ssa->status->label() }}</x-ui::badge>
                                @else
                                    <x-ui::badge variant="warning">No Active SSA</x-ui::badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-foreground/60">
                                No SSAs found in this category.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui::card>
</x-admin.layouts.app>
