<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Sub Requests</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Coverage requests you've been invited to, and the ones you've raised.
                </p>
            </div>

            @if (session('status'))
                <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
            @endif

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">{{ $errors->first() }}</x-ui::alert>
            @endif

            {{-- Tabs --}}
            <div class="mb-4 border-b border-border">
                <nav class="flex gap-1" role="tablist" aria-label="Sub request tabs">
                    @php
                        $tabs = [
                            'invited' => ['label' => 'Invited to Me', 'count' => $invitedCount],
                            'mine' => ['label' => 'My Requests', 'count' => $mineCount],
                        ];
                    @endphp
                    @foreach ($tabs as $key => $meta)
                        @php $isActive = $tab === $key; @endphp
                        <button
                            type="button"
                            data-sub-tab="{{ $key }}"
                            role="tab"
                            aria-selected="{{ $isActive ? 'true' : 'false' }}"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors
                                {{ $isActive
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-foreground/60 hover:text-foreground hover:border-border' }}">
                            {{ $meta['label'] }}
                            <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-foreground/70">
                                {{ $meta['count'] }}
                            </span>
                        </button>
                    @endforeach
                </nav>
            </div>

            <x-ui::card class="p-6">
                <div class="overflow-x-auto">
                    <table
                        id="subRequestsTable"
                        data-datatable-url="{{ $datatableUrl }}"
                        data-mode="{{ $tab }}"
                        class="min-w-full divide-y divide-border">
                        <thead class="bg-muted/40">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Date &amp; Time
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Student &amp; School
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    Service
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                    {{ $tab === 'mine' ? 'Invitees' : 'Requested By' }}
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

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-sub-requests.js'])
    </x-slot>
</x-app-layout>
