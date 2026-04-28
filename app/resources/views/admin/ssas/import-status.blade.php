<x-admin.layouts.app>
    <x-page-title title="Import Status" />

    <x-ui::card class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Import #{{ $import->id }}</h2>
                <p class="text-sm text-foreground/60 mt-1">
                    Type: <span class="font-medium">{{ $import->type->value }}</span> |
                    File: <a href="{{ route('admin.ssas.imports.download', $import) }}" class="font-medium text-primary hover:underline">{{ $import->file_name }}</a> |
                    Created: <span class="font-medium">{{ $import->created_at->format('M d, Y H:i') }}</span>
                </p>
            </div>
            <div>
                <span
                    class="px-3 py-1 text-xs font-semibold rounded-full
                    @if ($import->status->value === 'completed') bg-green-100 text-green-800
                    @elseif($import->status->value === 'failed') bg-red-100 text-red-800
                    @elseif($import->status->value === 'processing') bg-blue-100 text-blue-800
                    @else bg-yellow-100 text-yellow-800 @endif">
                    {{ strtoupper($import->status->value) }}
                </span>
            </div>
        </div>

        {{-- Progress Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-foreground/5 rounded-lg p-4">
                <div class="text-sm text-foreground/60">Total Rows</div>
                <div class="text-2xl font-semibold">{{ $stats['total'] }}</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="text-sm text-blue-700">Processed</div>
                <div class="text-2xl font-semibold text-blue-700">{{ $stats['processed'] }}</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4">
                <div class="text-sm text-green-700">Success</div>
                <div class="text-2xl font-semibold text-green-700">{{ $stats['success'] }}</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4">
                <div class="text-sm text-yellow-700">Duplicates</div>
                <div class="text-2xl font-semibold text-yellow-700">{{ $stats['duplicates'] }}</div>
            </div>
            <div class="bg-red-50 rounded-lg p-4">
                <div class="text-sm text-red-700">Errors</div>
                <div class="text-2xl font-semibold text-red-700">{{ $stats['errors'] }}</div>
            </div>
        </div>

        {{-- Progress Bar --}}
        @if ($stats['total'] > 0)
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-foreground">Processing Progress</span>
                    <span class="text-sm text-foreground/60">
                        {{ round(($stats['processed'] / $stats['total']) * 100, 1) }}%
                    </span>
                </div>
                <div class="w-full bg-foreground/10 rounded-full h-2.5">
                    <div class="bg-primary h-2.5 rounded-full transition-all duration-300"
                        style="width: {{ ($stats['processed'] / $stats['total']) * 100 }}%"></div>
                </div>
            </div>
        @endif

        @if ($import->error_message)
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-semibold text-red-800 mb-2">Import Error</h4>
                <p class="text-sm text-red-700">{{ $import->error_message }}</p>
            </div>
        @endif

        {{-- Row Details --}}
        <div class="border-t border-border pt-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Row Details</h3>
                <div class="flex gap-2">
                    <button id="filterAll"
                        class="px-3 py-1 text-xs border rounded-md hover:bg-foreground/5">All</button>
                    <button id="filterSuccess"
                        class="px-3 py-1 text-xs border rounded-md hover:bg-foreground/5">Success</button>
                    <button id="filterDuplicates"
                        class="px-3 py-1 text-xs border rounded-md hover:bg-foreground/5">Duplicates</button>
                    <button id="filterErrors"
                        class="px-3 py-1 text-xs border rounded-md hover:bg-foreground/5">Errors</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <thead class="bg-foreground/5">
                        <tr>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Row
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Status
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                SSA
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Error/Message
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                Processed At
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                                CSV Data
                            </th>
                        </tr>
                    </thead>
                    <tbody id="rowsTableBody" class="bg-white divide-y divide-border">
                        @foreach ($import->rows as $row)
                            <tr class="row-item" data-status="{{ $row->status->value }}">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">{{ $row->row_number }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="px-2 py-1 text-xs font-semibold rounded-full
                                        @if ($row->status->value === 'done') bg-green-100 text-green-800
                                        @elseif($row->status->value === 'duplicate') bg-yellow-100 text-yellow-800
                                        @elseif($row->status->value === 'validation_error') bg-red-100 text-red-800
                                        @elseif($row->status->value === 'processing') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ str_replace('_', ' ', ucwords($row->status->value, '_')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    @if ($row->ssa_id && $row->ssa)
                                        <a href="{{ route('admin.ssas.show', $row->ssa) }}"
                                            class="text-primary hover:text-primary/80">
                                            SSA #{{ $row->ssa->id }}
                                        </a>
                                    @else
                                        <span class="text-foreground/40">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-foreground/70">
                                    @if ($row->error_message)
                                        <span
                                            class="text-red-700">{{ \Illuminate\Support\Str::limit($row->error_message, 100) }}</span>
                                    @else
                                        <span class="text-foreground/40">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground/60">
                                    {{ $row->processed_at ? $row->processed_at->format('M d, Y H:i:s') : '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($row->raw_data)
                                        <button type="button"
                                            class="view-row-data px-2 py-1 text-xs border border-border rounded-md hover:bg-foreground/5 text-foreground/70"
                                            data-raw="{{ json_encode($row->raw_data) }}">
                                            View Data
                                        </button>
                                    @else
                                        <span class="text-foreground/40">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
            <a href="{{ route('admin.ssas.imports.index') }}"
                class="px-4 py-2 text-sm font-medium text-foreground/70 hover:text-foreground border border-border rounded-md hover:bg-foreground/5">
                Back to History
            </a>
            <a href="{{ route('admin.ssas.import') }}"
                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90">
                New Import
            </a>
        </div>
    </x-ui::card>

    {{-- Row CSV Data Modal --}}
    <div id="rowDataModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                <h3 class="text-lg font-semibold">Row CSV Data</h3>
                <button id="closeRowModal" type="button"
                    class="text-foreground/50 hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-auto max-h-[60vh]">
                <table id="rowDataTable" class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-foreground/5">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Field</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">Value</th>
                        </tr>
                    </thead>
                    <tbody id="rowDataBody" class="divide-y divide-border"></tbody>
                </table>
            </div>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-ssas-import-status.js'])
    </x-slot>
</x-admin.layouts.app>
