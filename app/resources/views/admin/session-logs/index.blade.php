<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Session Logs" />

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="adminSessionLogsFiltersForm">
            <x-slot:filters>
                <x-ui::select name="school_id" searchable placeholder="All Schools" :inline="true" class="w-36">
                    <option value="">All Schools</option>
                    @foreach ($schools ?? [] as $school)
                        <option value="{{ $school->id }}" @selected((int) ($filters['school_id'] ?? 0) === $school->id)>
                            {{ $school->display_name ?? ($school->name ?? 'School #' . $school->id) }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="therapist_id" searchable placeholder="All Therapists" :inline="true" class="w-40">
                    <option value="">All Therapists</option>
                    @foreach ($therapists ?? [] as $therapist)
                        <option value="{{ $therapist->id }}" @selected((int) ($filters['therapist_id'] ?? 0) === $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="ssa_id" searchable placeholder="All SSAs" :inline="true" class="w-36">
                    <option value="">All SSAs</option>
                    @foreach ($ssas ?? [] as $ssa)
                        <option value="{{ $ssa->id }}" @selected((int) ($filters['ssa_id'] ?? 0) === $ssa->id)>
                            {{ $ssa->primaryService?->name ?? 'Unnamed service' }}
                            @if ($ssa->student?->name)
                                ({{ $ssa->student->name }})
                            @endif
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="service_id" searchable placeholder="All Services" :inline="true" class="w-36">
                    <option value="">All Services</option>
                    @foreach ($services ?? [] as $service)
                        <option value="{{ $service->id }}" @selected((int) ($filters['service_id'] ?? 0) === $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                    title="From Date" class="w-36" />

                <x-ui::input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                    title="To Date" class="w-36" />

                @if (!empty(array_filter($filters ?? [])))
                    <a href="{{ route('admin.session-logs.index') }}">
                        <x-ui::button type="button" variant="secondary">Clear</x-ui::button>
                    </a>
                @endif
            </x-slot:filters>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="adminSessionLogsTable" class="min-w-full divide-y divide-border session-log-table display"
                data-datatable-url="{{ $datatableUrl ?? route('admin.session-logs.data') }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Date & Time / Duration</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Entry Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Student & School</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Therapist & Service</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">School Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Therapist Amount</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-border">
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/session-logs/index.js'])
    </x-slot>
</x-admin.layouts.app>
