{{--
    @var array<int, array{id: int, date: string, start: string, end: string, notes: string|null, delete_url: string}> $rows
    @var string $createUrl
--}}
<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Make-Up Availability</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Manage your availability windows for scheduling make-up sessions.
                </p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui::alert>
            @endif

            <x-ui::card class="p-6 space-y-4">
                <x-ui::filter-toolbar :actions-only="true">
                    <x-slot:actions>
                        <a href="{{ $createUrl }}">
                            <x-ui::button>Add Window</x-ui::button>
                        </a>
                    </x-slot:actions>
                </x-ui::filter-toolbar>

                @if (count($rows) === 0)
                    <div class="text-center py-10">
                        <p class="text-foreground/70 mb-4">No upcoming availability windows defined.</p>
                        <a href="{{ $createUrl }}">
                            <x-ui::button>Add Window</x-ui::button>
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table id="makeupAvailabilityTable" class="min-w-full divide-y divide-border">
                            <thead class="bg-muted/40">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Start</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">End</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Notes</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-background divide-y divide-border" id="availability-list">
                                @foreach ($rows as $row)
                                    <tr data-row-id="{{ $row['id'] }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-foreground">{{ $row['date'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-foreground">{{ $row['start'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-foreground">{{ $row['end'] }}</td>
                                        <td class="px-6 py-4 text-sm text-foreground/60">{{ $row['notes'] ?? '—' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button"
                                                    class="js-delete-availability inline-flex items-center justify-center w-8 h-8 rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring bg-danger text-danger-foreground hover:bg-danger/90"
                                                    data-delete-url="{{ $row['delete_url'] }}"
                                                    title="Delete" aria-label="Delete">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-makeup-availability.js'])
    </x-slot>
</x-app-layout>
