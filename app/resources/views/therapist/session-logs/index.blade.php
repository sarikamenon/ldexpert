<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Session Logs</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Review and manage your session logs using filters for students, SSAs, services, and dates.
                </p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <x-ui::filter-toolbar formId="sessionLogsFiltersForm">
                    <x-slot:filters>
                        <div class="space-y-1">
                            <label for="student_id" class="text-xs font-medium text-foreground/70">Student</label>
                            <x-ui::select id="student_id" name="student_id" searchable
                                placeholder="All Students" class="min-w-[10rem]">
                                <option value="">All Students</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}" @selected((int) ($filters['student_id'] ?? 0) === $student->id)>
                                        {{ $student->name }}
                                    </option>
                                @endforeach
                            </x-ui::select>
                        </div>

                        <div class="space-y-1">
                            <label for="ssa_id" class="text-xs font-medium text-foreground/70">SSA</label>
                            <x-ui::select id="ssa_id" name="ssa_id" searchable
                                placeholder="All SSAs" class="min-w-[10rem]">
                                <option value="">All SSAs</option>
                                @foreach ($ssas as $ssa)
                                    <option value="{{ $ssa->id }}" @selected((int) ($filters['ssa_id'] ?? 0) === $ssa->id)>
                                        {{ $ssa->primaryService?->name ?? 'Unnamed service' }}
                                        @if ($ssa->student?->name)
                                            ({{ $ssa->student->name }})
                                        @endif
                                    </option>
                                @endforeach
                            </x-ui::select>
                        </div>

                        <div class="space-y-1">
                            <label for="service_id" class="text-xs font-medium text-foreground/70">Service</label>
                            <x-ui::select id="service_id" name="service_id" searchable
                                placeholder="All Services" class="min-w-[10rem]">
                                <option value="">All Services</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}" @selected((int) ($filters['service_id'] ?? 0) === $service->id)>
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </x-ui::select>
                        </div>

                        <div class="space-y-1">
                            <label for="date_from" class="text-xs font-medium text-foreground/70">From Date</label>
                            <x-ui::input id="date_from" type="date" name="date_from"
                                value="{{ $filters['date_from'] ?? '' }}" class="w-40" />
                        </div>

                        <div class="space-y-1">
                            <label for="date_to" class="text-xs font-medium text-foreground/70">To Date</label>
                            <x-ui::input id="date_to" type="date" name="date_to"
                                value="{{ $filters['date_to'] ?? '' }}" class="w-40" />
                        </div>
                    </x-slot:filters>
                </x-ui::filter-toolbar>

                <div class="overflow-x-auto">
                    <table id="therapistSessionLogsTable" class="min-w-full divide-y divide-border session-log-table display"
                        data-datatable-url="{{ $datatableUrl ?? route('therapist.session-logs.data') }}"
                        data-filter-form="sessionLogsFiltersForm">
                        <thead class="bg-background/subtle">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Student & School/Family</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Therapist & Service</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Notes</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-border">
                        </tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/session-logs/index.js'])
    </x-slot>
</x-app-layout>
