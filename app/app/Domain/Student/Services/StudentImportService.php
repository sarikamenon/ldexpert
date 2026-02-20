<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Enums\StudentImportType;
use App\Domain\Storage\Services\StorageServiceInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\DTOs\ImportStudentDTO;
use App\DTOs\StoreStudentImportDTO;
use App\Enums\StudentImportRowStatus;
use App\Enums\StudentImportStatus;
use App\Jobs\ProcessStudentImportJob;
use App\Models\School;
use App\Models\StudentImport;
use App\Models\StudentImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class StudentImportService
{
    public function __construct(
        private readonly StudentRepositoryInterface $repository,
        private readonly StudentService $studentService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    public function storeImportRequest(StoreStudentImportDTO $dto): StudentImport
    {
        // Upload file to configured storage service
        $filePath = $this->storeFile($dto->file);

        // Count total rows in CSV
        $totalRows = $this->countCsvRows($dto->file);

        // Create import record
        $import = StudentImport::create([
            'user_id' => $dto->userId,
            'type' => $dto->type,
            'file_path' => $filePath,
            'file_name' => $dto->file->getClientOriginalName(),
            'total_rows' => $totalRows,
            'status' => StudentImportStatus::PENDING,
        ]);

        // Create import row records
        $this->createImportRowRecords($import, $totalRows);

        // Queue background job
        ProcessStudentImportJob::dispatch($import);

        return $import;
    }

    public function processImport(StudentImport $import): void
    {
        // Get template for import type
        $template = $this->getTemplate($import->type);

        // Validate file structure
        $structureErrors = $this->validateFileStructure($import, $template);
        if (! empty($structureErrors)) {
            $import->update([
                'status' => StudentImportStatus::FAILED,
                'error_message' => implode(' ', $structureErrors),
                'completed_at' => now(),
            ]);

            return;
        }

        // Parse CSV from storage
        $rows = $this->parseCsvFromStorage($import->file_path);

        // Process each row
        foreach ($rows as $rowNumber => $rowData) {
            $this->processRow($import, $rowNumber + 1, $rowData, $template);
        }

        // Update processed count
        $import->refresh();
        $import->update([
            'processed_rows' => $import->rows()->count(),
        ]);
    }

    public function processRow(StudentImport $import, int $rowNumber, array $rowData, array $template): void
    {
        $importRow = StudentImportRow::where('student_import_id', $import->id)
            ->where('row_number', $rowNumber)
            ->first();

        if (! $importRow) {
            return;
        }

        // Update row status to processing
        $importRow->update([
            'status' => StudentImportRowStatus::PROCESSING,
            'raw_data' => $rowData,
        ]);

        try {
            // Map columns using template
            $mappedData = $this->mapColumns($rowData, $template);

            // Look up school by external_emr_name (exact match)
            $schoolName = $mappedData['school_name'] ?? null;
            if (! $schoolName) {
                $importRow->update([
                    'status' => StudentImportRowStatus::VALIDATION_ERROR,
                    'error_message' => 'School name is required.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $school = $this->lookupSchoolByExternalEmrName($schoolName);
            if (! $school) {
                $importRow->update([
                    'status' => StudentImportRowStatus::VALIDATION_ERROR,
                    'error_message' => "School with name '{$schoolName}' not found.",
                    'processed_at' => now(),
                ]);

                return;
            }

            // Apply type-specific transformations
            $mappedData = $this->applyTypeSpecificTransformations($mappedData, $template, $import->type, $school);

            // Normalize parent_guardian_phone (e.g. (385) 497-0814 -> 385-497-0814)
            if (! empty($mappedData['parent_guardian_phone'])) {
                $mappedData['parent_guardian_phone'] = $this->normalizePhone($mappedData['parent_guardian_phone']);
            }

            // Resolve timezone: accept key or display label, fallback to school timezone
            $resolvedTimezone = UsTimezones::resolveFromInput($mappedData['timezone'] ?? null);
            $mappedData['timezone'] = $resolvedTimezone ?? $school->timezone;

            // Validate row
            $importDTO = ImportStudentDTO::fromArray($mappedData, $rowNumber);
            $validationErrors = $this->validateRow($importDTO, $school->id);

            if (! empty($validationErrors)) {
                $importRow->update([
                    'status' => StudentImportRowStatus::VALIDATION_ERROR,
                    'error_message' => implode('; ', $validationErrors),
                    'processed_at' => now(),
                ]);

                return;
            }

            // Check for duplicates
            $duplicateCheck = $this->checkDuplicate($mappedData, $school->id);
            if ($duplicateCheck !== null) {
                $importRow->update([
                    'status' => StudentImportRowStatus::DUPLICATE,
                    'error_message' => $duplicateCheck,
                    'processed_at' => now(),
                ]);

                return;
            }

            // Create student
            $password = Str::password(12);
            $createDTO = $importDTO->toCreateStudentDTO($password, $school->id);
            $student = $this->studentService->create($createDTO);

            // Update row status to done
            $importRow->update([
                'status' => StudentImportRowStatus::DONE,
                'student_id' => $student->user_id,
                'processed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $importRow->update([
                'status' => StudentImportRowStatus::VALIDATION_ERROR,
                'error_message' => 'Failed to process row: '.$e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    public function lookupSchoolByExternalEmrName(string $externalEmrName): ?School
    {
        return $this->schoolRepository->findByExternalEmrName($externalEmrName);
    }

    public function validateFileStructure(StudentImport $import, array $template): array
    {
        $errors = [];

        $fileContent = $this->storageService->get($import->file_path);
        if ($fileContent === false) {
            return ['Unable to read file from storage.'];
        }

        // Use temporary file to parse CSV properly
        $tempFile = tmpfile();
        if ($tempFile === false) {
            return ['Unable to parse file.'];
        }

        fwrite($tempFile, $fileContent);
        rewind($tempFile);

        // Read headers
        $headers = fgetcsv($tempFile);
        fclose($tempFile);

        if ($headers === false || empty($headers)) {
            return ['File appears to be empty or invalid.'];
        }

        // Normalize headers
        $headers = array_map('trim', $headers);

        // Check required columns
        $requiredColumns = $template['required_columns'] ?? [];
        $missingColumns = [];

        foreach ($requiredColumns as $requiredColumn) {
            if (! in_array($requiredColumn, $headers, true)) {
                $missingColumns[] = $requiredColumn;
            }
        }

        if (! empty($missingColumns)) {
            $errors[] = 'Missing required columns: '.implode(', ', $missingColumns);
        }

        return $errors;
    }

    public function parseCsvFromStorage(string $filePath): array
    {
        $rows = [];

        $fileContent = $this->storageService->get($filePath);
        if ($fileContent === false) {
            return [];
        }

        // Use temporary file to parse CSV properly
        $tempFile = tmpfile();
        if ($tempFile === false) {
            return [];
        }

        fwrite($tempFile, $fileContent);
        rewind($tempFile);

        // Read headers
        $headers = fgetcsv($tempFile);
        if ($headers === false || empty($headers)) {
            fclose($tempFile);

            return [];
        }

        // Normalize headers
        $headers = array_map('trim', $headers);

        // Parse data rows
        while (($row = fgetcsv($tempFile)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Combine headers with row data
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = trim($row[$index] ?? '');
            }

            $rows[] = $rowData;
        }

        fclose($tempFile);

        return $rows;
    }

    public function mapColumns(array $rowData, array $template): array
    {
        $mapped = [];
        $columnMapping = $template['column_mapping'] ?? [];

        foreach ($columnMapping as $csvColumn => $fieldName) {
            if (isset($rowData[$csvColumn])) {
                $mapped[$fieldName] = trim($rowData[$csvColumn]);
            }
        }

        return $mapped;
    }

    public function validateRow(ImportStudentDTO $dto, int $schoolId): array
    {
        $data = $dto->data;
        $errors = [];

        // Use Laravel validator with rules from StudentFormRequest
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'gender' => ['required', 'string', 'max:50'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'id_number' => ['required', 'string', 'max:50'],
            'timezone' => ['required', 'string'],
            'grade_level' => ['required', 'string', 'max:50'],
            'parent_guardian_name' => ['nullable', 'string', 'max:255'],
            'parent_guardian_email' => ['nullable', 'email:rfc', 'max:255'],
            'parent_guardian_phone' => ['nullable', 'regex:/^[\d-]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string'],
            'zip_code' => ['required', 'string', 'max:20'],
        ];

        // Validate timezone (after resolution - must be valid key)
        if (isset($data['timezone']) && $data['timezone'] !== '' && ! array_key_exists($data['timezone'], UsTimezones::TIMEZONES)) {
            $errors[] = 'Invalid timezone.';
        }

        // Validate state
        if (isset($data['state'])) {
            $stateCode = $this->normalizeState($data['state']);
            if ($stateCode === null) {
                $errors[] = 'Invalid state.';
            } else {
                $data['state'] = $stateCode;
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        return $errors;
    }

    public function checkDuplicate(array $data, int $schoolId): ?string
    {
        // Check by email (globally unique)
        if (isset($data['email'])) {
            $existing = $this->repository->findByEmail($data['email']);
            if ($existing !== null) {
                return 'Student with email "'.$data['email'].'" already exists.';
            }
        }

        // Check by id_number per school
        if (isset($data['id_number'])) {
            $existing = $this->repository->findByIdNumber($data['id_number'], $schoolId);
            if ($existing !== null) {
                return 'Student with ID number "'.$data['id_number'].'" already exists for this school.';
            }
        }

        return null;
    }

    public function getTemplate(\App\Enums\StudentImportType $type): array
    {
        $config = config('student-import');
        $typeKey = $type->value;

        return $config['templates'][$typeKey] ?? [];
    }

    private function storeFile(UploadedFile $file): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $filename = now()->format('Ymd_His').'_'.Str::random(8).'_'.$file->getClientOriginalName();

        $path = config('student-import.s3.path_prefix', 'student-imports')."/{$year}/{$month}/{$filename}";

        $this->storageService->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    private function countCsvRows(UploadedFile $file): int
    {
        $count = 0;
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return 0;
        }

        // Skip header
        fgetcsv($handle);

        // Count data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (! empty(array_filter($row))) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    private function createImportRowRecords(StudentImport $import, int $totalRows): void
    {
        $rows = [];
        for ($i = 1; $i <= $totalRows; $i++) {
            $rows[] = [
                'student_import_id' => $import->id,
                'row_number' => $i,
                'status' => StudentImportRowStatus::PENDING,
                'raw_data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($rows, 500) as $chunk) {
            StudentImportRow::insert($chunk);
        }
    }

    private function applyTypeSpecificTransformations(array $mappedData, array $template, StudentImportType $type, School $school): array
    {
        if ($type !== StudentImportType::RSM) {
            return $mappedData;
        }

        // RSM: use parent email as student email
        $mappedData['email'] = trim($mappedData['parent_guardian_email'] ?? '');

        // RSM: default date of birth
        $mappedData['date_of_birth'] = $template['default_date_of_birth'] ?? '2020-02-20';

        // RSM: state from school
        $mappedData['state'] = $school->state_code ?? $school->getAttributes()['state'] ?? null;

        // RSM: build parent_guardian_name from Parent First Name + Parent Last Name
        $parentFirst = trim($mappedData['parent_guardian_first_name'] ?? '');
        $parentLast = trim($mappedData['parent_guardian_last_name'] ?? '');
        $mappedData['parent_guardian_name'] = trim($parentFirst.' '.$parentLast) ?: null;
        unset($mappedData['parent_guardian_first_name'], $mappedData['parent_guardian_last_name']);

        return $mappedData;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10) {
            return substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6, 4);
        }

        return $digits;
    }

    private function normalizeState(string $state): ?string
    {
        $normalized = strtoupper(trim($state));

        // Check if it's already a state code
        if (array_key_exists($normalized, UsStates::STATES)) {
            return $normalized;
        }

        // Check if it's a state name
        foreach (UsStates::STATES as $code => $name) {
            if (strcasecmp($name, $state) === 0) {
                return $code;
            }
        }

        return null;
    }
}
