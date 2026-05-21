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

            <x-ui::card class="p-6">
                <form id="makeup-filter-form" class="mb-4 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3" onsubmit="return false;">
                    <div class="flex items-end gap-3">
                        <div>
                            <label for="makeup-status-filter" class="block text-xs font-medium text-foreground/70 mb-1">
                                Status
                            </label>
                            <select
                                id="makeup-status-filter"
                                name="filter_status"
                                class="px-3 py-2 text-sm rounded-md border border-border bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
                                <option value="">All</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-ui::button type="submit" id="makeup-filter-apply">Filter</x-ui::button>
                    </div>
                    <div class="text-sm text-foreground/60">
                        {{ $totalCount }} total
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table
                        id="makeupRequestsTable"
                        data-datatable-url="{{ $datatableUrl }}"
                        class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/40">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Closure Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Student &amp; School
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Service
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Closure
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Status
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
        <div class="flex flex-col bg-background">
            {{-- Header --}}
            <div class="flex items-start justify-between gap-3 px-6 pt-6 pb-5 border-b border-border">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-xl font-semibold text-foreground">Make-up request</h2>
                        <span id="makeup-modal-status-pill"></span>
                    </div>
                    <div id="makeup-modal-subtitle" class="mt-2 flex items-center gap-2 text-sm text-foreground/70"></div>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-foreground/40 hover:text-foreground rounded-md p-1 -m-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    x-on:click="$dispatch('close-modal', 'makeup-request-detail')"
                    aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Student strip --}}
            <div id="makeup-modal-student" class="px-6 py-4 border-b border-border"></div>

            {{-- Status-driven body --}}
            <div id="makeup-modal-body" class="px-6 py-5">
                <div class="text-center py-8 text-sm text-foreground/60">Loading...</div>
            </div>

            {{-- Footer --}}
            <div id="makeup-modal-footer" class="flex items-center justify-between gap-3 px-6 py-4 border-t border-border"></div>
        </div>
    </x-modal>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-makeup-requests.js'])
    </x-slot>
</x-app-layout>
