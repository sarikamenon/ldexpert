<x-admin.layouts.app>
    <x-page-title title="Import History" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Student Import History</h2>
            <a href="{{ route('admin.students.import') }}"
                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90">
                New Import
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border" id="importsTable">
                <thead class="bg-foreground/5">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            ID
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Type
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            File Name
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Progress
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Created
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-border">
                    @foreach ($imports as $import)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">#{{ $import->id }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $import->type->value }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $import->file_name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">{{ $import->user->name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-semibold rounded-full
                                    @if ($import->status->value === 'completed') bg-green-100 text-green-800
                                    @elseif($import->status->value === 'failed') bg-red-100 text-red-800
                                    @elseif($import->status->value === 'processing') bg-blue-100 text-blue-800
                                    @else bg-yellow-100 text-yellow-800 @endif">
                                    {{ strtoupper($import->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $import->processed_rows }} / {{ $import->total_rows }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground/60">
                                {{ $import->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <a href="{{ route('admin.students.imports.show', $import) }}"
                                    class="text-primary hover:text-primary/80">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $imports->links() }}
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-students-import-history.js'])
    </x-slot>
</x-admin.layouts.app>
