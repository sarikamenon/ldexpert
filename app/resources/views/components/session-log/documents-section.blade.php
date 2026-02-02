@props(['sessionLog', 'documents', 'context' => 'therapist'])

<div class="space-y-6">
    {{-- Upload Form --}}
    @if ($context === 'therapist')
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Upload Document</h3>
            <form id="session-log-document-upload-form"
                action="{{ route('therapist.session-logs.documents.store', $sessionLog) }}"
                method="POST" enctype="multipart/form-data">
                @csrf
                <div class="space-y-4">
                    <div>
                        <x-input-label for="file" value="File *" />
                        <p class="mt-1 text-xs text-foreground/60" id="file_help">
                            Upload a document file (PDF, DOC, DOCX, JPG, JPEG, PNG). Maximum file size: 10MB.
                        </p>
                        <x-ui::file-input id="file" name="file"
                            class="mt-1"
                            aria-describedby="file_help" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="document_type" value="Document Type *" />
                        <p class="mt-1 text-xs text-foreground/60" id="document_type_help">
                            Select the type of document you are uploading.
                        </p>
                        <x-ui::select id="document_type" name="document_type" class="mt-1"
                            aria-describedby="document_type_help" required>
                            <option value="">Select a type</option>
                            @foreach (\App\Enums\DocumentType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </x-ui::select>
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
        <h3 class="text-lg font-semibold text-foreground mb-4">Documents</h3>
        <div id="documents-list" class="space-y-4">
            @if ($documents->count() > 0)
                @foreach ($documents as $document)
                    <div class="border-b border-border pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-foreground">{{ $document->file_name }}</span>
                                    <x-ui::badge variant="primary">
                                        {{ $document->document_type->label() }}
                                    </x-ui::badge>
                                </div>
                                @if ($document->description)
                                    <p class="text-sm text-foreground/70 mb-1">{{ $document->description }}</p>
                                @endif
                                <div class="flex items-center gap-4 text-xs text-foreground/60">
                                    <span>Uploaded by {{ $document->uploadedBy->name ?? '—' }}</span>
                                    <span>{{ $document->created_at->format('M d, Y g:i A') }}</span>
                                    <span>{{ $document->formatted_file_size }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                @if ($context === 'therapist')
                                    <a href="{{ route('therapist.session-logs.documents.download', ['sessionLog' => $sessionLog, 'document' => $document]) }}"
                                        class="text-primary hover:text-primary/80 text-sm font-medium">
                                        Download
                                    </a>
                                    @if ($document->uploaded_by_id === auth()->id())
                                        <button type="button" class="delete-document-btn text-danger hover:text-danger/80 text-sm font-medium"
                                            data-document-id="{{ $document->id }}"
                                            data-session-log-id="{{ $sessionLog->id }}">
                                            Delete
                                        </button>
                                    @endif
                                @else
                                    <a href="{{ route('admin.student-documents.download', $document) }}"
                                        class="text-primary hover:text-primary/80 text-sm font-medium">
                                        Download
                                    </a>
                                    <button type="button" class="delete-document-btn text-danger hover:text-danger/80 text-sm font-medium"
                                        data-document-id="{{ $document->id }}">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-8">
                    <p class="text-sm text-foreground/60">No documents yet.@if ($context === 'therapist') Upload a document to get started!@endif</p>
                </div>
            @endif
        </div>
    </x-ui::card>
</div>
