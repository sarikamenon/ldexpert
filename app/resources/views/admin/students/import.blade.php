<x-admin.layouts.app>
    <x-page-title title="Import Students" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6 space-y-6">
        <div>
            <h2 class="text-xl font-semibold mb-2">Import Students from CSV</h2>
            <p class="text-sm text-foreground/60">
                Upload a CSV file containing student information. The file must include all required columns.
                Duplicate students (by email or ID number per school) will be skipped automatically.
                The import will be processed in the background and you will receive an email notification when it
                completes.
            </p>
        </div>

        {{-- Import Type Selection --}}
        <div>
            <x-input-label for="import_type" value="Import Type *" />
            <p class="mt-1 text-xs text-foreground/60" id="import_type_help">
                Select the source system for this import. The template requirements vary by type.
            </p>
            <select name="import_type" id="import_type"
                class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                aria-describedby="import_type_help">
                <option value="">Select import type</option>
                @foreach ($importTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('import_type', 'NOVA') === $type->value)>
                        {{ $type->value }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        {{-- Template Information --}}
        <div id="templateInfo" class="bg-foreground/5 rounded-lg p-4 space-y-3">
            <h3 class="font-semibold text-sm">Required Columns</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($requiredColumns as $column)
                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded">{{ $column }}</span>
                @endforeach
            </div>

            @if (!empty($optionalColumns))
                <h3 class="font-semibold text-sm mt-4">Optional Columns</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($optionalColumns as $column)
                        <span
                            class="px-2 py-1 bg-foreground/10 text-foreground/70 text-xs rounded">{{ $column }}</span>
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                <a href="{{ route('admin.students.import.template', ['type' => 'NOVA']) }}" id="templateDownloadLink"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-primary hover:text-primary/80">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Download CSV Template
                </a>
            </div>
        </div>

        {{-- Import Form --}}
        <form id="importForm" method="POST" action="{{ route('admin.students.import.store') }}"
            enctype="multipart/form-data" class="space-y-4">
            @csrf

            <input type="hidden" name="type" id="type" value="NOVA" />

            <div>
                <x-input-label for="file" value="CSV File *" />
                <p class="mt-1 text-xs text-foreground/60" id="file_help">
                    Select a CSV file containing student data. Maximum file size: 10MB.
                    The file will be uploaded to S3 and processed in the background.
                </p>
                <x-ui::file-input id="file" name="file" accept=".csv,.txt" class="mt-1"
                    aria-describedby="file_help" required />
                <x-input-error :messages="$errors->get('file')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <a href="{{ route('admin.students.index') }}"
                    class="px-4 py-2 text-sm font-medium text-foreground/70 hover:text-foreground border border-border rounded-md hover:bg-foreground/5">
                    Cancel
                </a>
                <button type="submit" id="submitButton"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="submitButtonText">Queue Import</span>
                    <span id="submitButtonSpinner" class="hidden">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Uploading...
                    </span>
                </button>
            </div>
        </form>

        <div class="mt-4 pt-4 border-t border-border">
            <a href="{{ route('admin.students.imports.index') }}"
                class="text-sm text-primary hover:text-primary/80 inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                View Import History
            </a>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-students-import.js'])
    </x-slot>
</x-admin.layouts.app>
