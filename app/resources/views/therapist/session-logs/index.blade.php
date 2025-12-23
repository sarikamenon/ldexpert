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
                <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
                    <form method="GET" class="flex flex-wrap gap-3 items-end" id="sessionLogsFiltersForm">
                        <div class="space-y-1">
                            <label for="student_id" class="text-xs font-medium text-foreground/70">Student</label>
                            <div class="relative">
                                <select id="student_id" name="student_id"
                                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary min-w-[10rem]">
                                    <option value="">All Students</option>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}" @selected((int) ($filters['student_id'] ?? 0) === $student->id)>
                                            {{ $student->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="ssa_id" class="text-xs font-medium text-foreground/70">SSA</label>
                            <div class="relative">
                                <select id="ssa_id" name="ssa_id"
                                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary min-w-[10rem]">
                                    <option value="">All SSAs</option>
                                    @foreach ($ssas as $ssa)
                                        <option value="{{ $ssa->id }}" @selected((int) ($filters['ssa_id'] ?? 0) === $ssa->id)>
                                            {{ $ssa->primaryService?->name ?? 'Unnamed service' }}
                                            @if ($ssa->student?->name)
                                                ({{ $ssa->student->name }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="service_id" class="text-xs font-medium text-foreground/70">Service</label>
                            <div class="relative">
                                <select id="service_id" name="service_id"
                                    class="border border-border rounded-lg pl-3 pr-10 py-2 text-sm appearance-none focus:ring-2 focus:ring-primary focus:border-primary min-w-[10rem]">
                                    <option value="">All Services</option>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}" @selected((int) ($filters['service_id'] ?? 0) === $service->id)>
                                            {{ $service->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="date_from" class="text-xs font-medium text-foreground/70">From Date</label>
                            <div class="relative">
                                <input id="date_from" type="date" name="date_from"
                                    value="{{ $filters['date_from'] ?? '' }}"
                                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary w-40" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label for="date_to" class="text-xs font-medium text-foreground/70">To Date</label>
                            <div class="relative">
                                <input id="date_to" type="date" name="date_to"
                                    value="{{ $filters['date_to'] ?? '' }}"
                                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary w-40" />
                            </div>
                        </div>

                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 text-sm font-medium">
                            Filter
                        </button>

                        @if (!empty(array_filter($filters ?? [])))
                            <a href="{{ route('therapist.session-logs.index') }}"
                                class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-background/subtle">
                                Clear
                            </a>
                        @endif
                    </form>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('therapist.session-logs.select-ssa') }}"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            + Add Session Log
                        </a>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <x-ui::session-log-table :columns="$columns" :rows="$rows" />
                </div>

                <div class="mt-4">
                    {{ $sessionLogs->withQueryString()->links() }}
                </div>
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/session-logs/index.js'])
    </x-slot>
</x-app-layout>
