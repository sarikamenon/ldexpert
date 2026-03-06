<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <x-page-title title="Student Documents" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-6">
        <x-ui::filter-toolbar formId="studentDocumentsFiltersForm">
            <x-slot:filters>
                <x-ui::select name="student_id" searchable placeholder="All Students" :inline="true" class="w-40">
                    <option value="">All Students</option>
                    @foreach ($students as $student)
                        <option value="{{ $student['id'] }}" @selected(($filters['student_id'] ?? null) == $student['id'])>
                            {{ $student['name'] }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="document_type" :searchable="false" placeholder="All Types" :inline="true" class="w-32">
                    <option value="">All Types</option>
                    @foreach ($documentTypes as $type)
                        <option value="{{ $type->value }}" @selected(($filters['document_type'] ?? null) === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::select name="uploaded_by_id" searchable placeholder="All Users" :inline="true" class="w-36">
                    <option value="">All Users</option>
                    @foreach ($uploadedByUsers as $user)
                        <option value="{{ $user['id'] }}" @selected(($filters['uploaded_by_id'] ?? null) == $user['id'])>
                            {{ $user['name'] }}
                        </option>
                    @endforeach
                </x-ui::select>

                <x-ui::input type="text" name="search" placeholder="Search file name..."
                    value="{{ $filters['search'] ?? '' }}" class="w-48" />
            </x-slot:filters>
        </x-ui::filter-toolbar>

        @if ($documents->total() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Student</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Document Type</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">File Name</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Uploaded By</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Upload Date</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Size</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr class="border-b border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm text-foreground">
                                    @if ($document->isForStudent())
                                        {{ $document->documentable->name ?? '—' }}
                                    @elseif ($document->isForSessionLog())
                                        {{ $document->documentable->student->name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->document_type->label() }}
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    <span class="truncate max-w-xs block" title="{{ $document->file_name }}">
                                        {{ $document->file_name }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->uploadedBy->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->created_at->format('M d, Y') }}
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->formatted_file_size }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('admin.student-documents.download', $document) }}"
                                            class="text-primary hover:text-primary/80 text-sm font-medium"
                                            title="Download">
                                            Download
                                        </a>
                                        <button type="button"
                                            class="delete-document-btn text-danger hover:text-danger/80 text-sm font-medium"
                                            data-document-id="{{ $document->id }}" title="Delete">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $documents->links() }}
            </div>
        @else
            <x-ui::empty-state title="No documents found."
                description="Try adjusting your filters or upload a new document.">
                <x-slot:icon>
                    <svg class="mx-auto h-12 w-12 text-foreground/40" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </x-slot:icon>
            </x-ui::empty-state>
        @endif
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-student-documents.js'])
    </x-slot>
</x-admin.layouts.app>
