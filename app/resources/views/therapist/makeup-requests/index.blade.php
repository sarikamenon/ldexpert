<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Make-Up Requests</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Closures where you have a scheduled session — track parent responses and book make-ups.
                </p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <x-ui::filter-toolbar formId="makeup-filter-form">
                    <x-slot:filters>
                        <div class="w-[160px] [&_.select2-container]:!w-full">
                            <x-ui::select name="filter_status" :searchable="false" :inline="true">
                                <option value="">All</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ui::select>
                        </div>
                    </x-slot:filters>

                    <x-slot:actions>
                        <div class="text-sm text-foreground/60 self-center">
                            {{ $totalCount }} total
                        </div>
                    </x-slot:actions>
                </x-ui::filter-toolbar>

                <div class="overflow-x-auto">
                    <table
                        id="makeupRequestsTable"
                        data-datatable-url="{{ $datatableUrl }}"
                        class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/40">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Event Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Student &amp; School
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Service
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Event
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Reason
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-background divide-y divide-border">
                            {{-- populated by DataTables --}}
                        </tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>
    </div>

    {{-- Detail modal --}}
    <x-modal name="makeup-request-detail" max-width="lg">
        <div id="makeup-modal-content" class="flex flex-col bg-background">
            <div class="px-6 py-12 text-center text-sm text-foreground/60">Loading...</div>
        </div>
    </x-modal>

    {{-- Schedule details modal (opened when clicking the linked make-up session) --}}
    <x-schedule.schedule-details-modal />

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-makeup-requests.js'])
    </x-slot>
</x-app-layout>
