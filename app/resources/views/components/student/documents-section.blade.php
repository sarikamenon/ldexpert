@props(['student', 'documents', 'context' => 'admin'])

<div class="space-y-6">
    @php
        $showUpload = $errors->any()
            || old('document_type')
            || old('description');
    @endphp

    {{-- Upload Form --}}
    @if ($context === 'admin')
        <x-ui::card id="document-upload-card" class="p-6 {{ $showUpload ? '' : 'hidden' }}">
            <h3 class="text-lg font-semibold text-foreground mb-4">Upload Document</h3>
            <form id="document-upload-form"
                action="{{ route('admin.student-documents.store', $student) }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4">
                    <div>
                        <x-input-label for="file" value="File *" />
                        <p class="mt-1 text-xs text-foreground/60" id="file_help">
                            Upload a document file (PDF, DOC, DOCX, JPG, JPEG, PNG). Maximum file size: 10MB.
                        </p>
                        <x-ui-file-input id="file" name="file"
                            class="mt-1"
                            aria-describedby="file_help" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="document_type" value="Document Type *" />
                        <p class="mt-1 text-xs text-foreground/60" id="document_type_help">
                            Select the type of document you are uploading.
                        </p>
                        <select id="document_type" name="document_type"
                            class="mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                            aria-describedby="document_type_help" required>
                            <option value="">Select a type</option>
                            @foreach (\App\Enums\DocumentType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('document_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description" />
                        <p class="mt-1 text-xs text-foreground/60" id="description_help">
                            Optional description or notes about this document.
                        </p>
                        <textarea id="description" name="description" rows="3"
                            class="mt-1 block w-full border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary"
                            aria-describedby="description_help" maxlength="1000">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <button type="submit" id="submit-document-btn"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            <span id="submit-text">Upload Document</span>
                            <span id="submit-spinner" class="hidden ml-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </x-ui::card>
    @endif

    {{-- Documents List --}}
    <x-ui::card class="p-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h3 class="text-lg font-semibold text-foreground">Documents</h3>
            @if ($context === 'admin')
                <button type="button" id="toggle-document-upload"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium"
                    aria-controls="document-upload-card" aria-expanded="{{ $showUpload ? 'true' : 'false' }}">
                    <span data-label>{{ $showUpload ? 'Hide Upload' : 'Add Document' }}</span>
                </button>
            @endif
        </div>

        @if ($documents->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-border">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">File Name</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Type</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Uploaded By</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Uploaded At</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-foreground">Size</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr class="border-b border-border hover:bg-background/subtle">
                                <td class="py-3 px-4 text-sm text-foreground">
                                    <div class="font-semibold text-foreground">
                                        <span class="truncate max-w-xs block" title="{{ $document->file_name }}">
                                            {{ $document->file_name }}
                                        </span>
                                    </div>
                                    @if ($document->description)
                                        <p class="text-xs text-foreground/60 mt-1">{{ $document->description }}</p>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    <div class="flex items-center gap-2">
                                        <x-ui::badge variant="primary">
                                            {{ $document->document_type->label() }}
                                        </x-ui::badge>
                                        @if ($document->isForSessionLog())
                                            <x-ui::badge variant="success">
                                                Session Log
                                            </x-ui::badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->uploadedBy->name ?? '—' }}
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->created_at->format('M d, Y g:i A') }}
                                </td>
                                <td class="py-3 px-4 text-sm text-foreground">
                                    {{ $document->formatted_file_size }}
                                </td>
                                <td class="py-3 px-4 text-sm text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($context === 'admin')
                                            <a href="{{ route('admin.student-documents.download', $document) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                title="Download"
                                                aria-label="Download {{ $document->file_name }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                    </path>
                                                </svg>
                                            </a>
                                            <button type="button"
                                                class="delete-document-btn inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                data-document-id="{{ $document->id }}"
                                                title="Delete"
                                                aria-label="Delete {{ $document->file_name }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </button>
                                        @else
                                            @if ($document->isForSessionLog())
                                                <a href="{{ route('therapist.session-logs.documents.download', ['sessionLog' => $document->documentable_id ?? 0, 'document' => $document]) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    title="Download"
                                                    aria-label="Download {{ $document->file_name }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path
                                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                        </path>
                                                    </svg>
                                                </a>
                                                @if ($document->uploaded_by_id === auth()->id())
                                                    <button type="button"
                                                        class="delete-document-btn inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        data-document-id="{{ $document->id }}"
                                                        data-session-log-id="{{ $document->documentable_id ?? 0 }}"
                                                        title="Delete"
                                                        aria-label="Delete {{ $document->file_name }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path
                                                                d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                            </path>
                                                            <path
                                                                d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6">
                                                            </path>
                                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                                        </svg>
                                                    </button>
                                                @endif
                                            @else
                                                <a href="{{ route('admin.student-documents.download', $document) }}"
                                                    class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    title="Download"
                                                    aria-label="Download {{ $document->file_name }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path
                                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                        </path>
                                                    </svg>
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui::empty-state title="No documents yet."
                description="{{ $context === 'admin' ? 'Upload a document to get started.' : 'No documents have been uploaded yet.' }}">
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
</div>
