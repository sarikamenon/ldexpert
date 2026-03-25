<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="QGlob Requests" description="Review and create Q-Global access requests.">
        <x-slot name="actions">
            <a href="{{ route('admin.qglob-requests.create') }}">
                <x-ui::button class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    New Request
                </x-ui::button>
            </a>
        </x-slot>
    </x-page-title>

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-4">
        <x-ui::filter-toolbar formId="adminQglobRequestsFiltersForm">
            <x-slot:filters>
                <x-ui::select name="therapist_id" searchable placeholder="All therapists" :inline="true" class="w-44">
                    <option value="">All therapists</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="status" placeholder="All statuses" :inline="true" class="w-36">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="date" name="date_from" title="From date" class="w-36" />
                <x-ui::input type="date" name="date_to" title="To date" class="w-36" />
            </x-slot:filters>
        </x-ui::filter-toolbar>

        <div class="overflow-x-auto">
            <table id="adminQglobRequestsTable" class="min-w-full divide-y divide-border display"
                data-datatable-url="{{ $datatableUrl }}">
                <thead class="bg-background/subtle">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Date &amp; time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Therapist</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-qglob-requests.js'])
    </x-slot>
</x-admin.layouts.app>
