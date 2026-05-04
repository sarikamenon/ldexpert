<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-foreground">QGlob Requests</h1>
                    <p class="text-sm text-foreground/60 mt-1">
                        Submit and track Q-Global access requests for evaluation sessions.
                    </p>
                </div>
                <a href="{{ route('therapist.qglob-requests.create') }}">
                    <x-ui::button class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        New Request
                    </x-ui::button>
                </a>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <x-ui::filter-toolbar formId="qglobRequestsFiltersForm">
                    <x-slot:filters>
                        <div class="space-y-1">
                            <label for="filter_status" class="text-xs font-medium text-foreground/70">Status</label>
                            <x-ui::select id="filter_status" name="status" placeholder="All statuses" class="min-w-[10rem]">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                @endforeach
                            </x-ui::select>
                        </div>
                        <div class="space-y-1">
                            <label for="filter_date_from" class="text-xs font-medium text-foreground/70">From date</label>
                            <x-ui::input id="filter_date_from" type="date" name="date_from" class="w-40" />
                        </div>
                        <div class="space-y-1">
                            <label for="filter_date_to" class="text-xs font-medium text-foreground/70">To date</label>
                            <x-ui::input id="filter_date_to" type="date" name="date_to" class="w-40" />
                        </div>
                    </x-slot:filters>
                </x-ui::filter-toolbar>

                <div class="overflow-x-auto">
                    <table id="therapistQglobRequestsTable"
                        class="min-w-full divide-y divide-border display"
                        data-datatable-url="{{ $datatableUrl }}">
                        <thead class="bg-background/subtle">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Date &amp; time</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Student</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-qglob-requests.js'])
    </x-slot>
</x-app-layout>
