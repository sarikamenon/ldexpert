<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\Storage\Services\StorageServiceInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateSSADTO;
use App\DTOs\StoreSSAImportDTO;
use App\Enums\ServiceFrequency;
use App\Enums\ServiceStatus;
use App\Enums\SSAImportRowStatus;
use App\Enums\SSAImportStatus;
use App\Enums\SSAImportType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Jobs\ProcessSSAImportJob;
use App\Models\School;
use App\Models\Service;
use App\Models\SSAImport;
use App\Models\SSAImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SSAImportService
{
    public function __construct(
        private readonly SSARepositoryInterface $ssaRepository,
        private readonly SSAService $ssaService,
        private readonly StudentRepositoryInterface $studentRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    public function storeImportRequest(StoreSSAImportDTO $dto): SSAImport
    {
        // Upload file to configured storage service
        $filePath = $this->storeFile($dto->file);

        // Count total rows in CSV
        $totalRows = $this->countCsvRows($dto->file);

        // Create import record
        $import = SSAImport::create([
            'user_id' => $dto->userId,
            'type' => $dto->type,
            'file_path' => $filePath,
            'file_name' => $dto->file->getClientOriginalName(),
            'total_rows' => $totalRows,
            'status' => SSAImportStatus::PENDING,
        ]);

        // Create import row records
        $this->createImportRowRecords($import, $totalRows);

        // Queue background job
        ProcessSSAImportJob::dispatch($import);

        return $import;
    }

    public function processImport(SSAImport $import): void
    {
        // Get template for import type
        $template = $this->getTemplate($import->type);

        // Validate file structure
        $structureErrors = $this->validateFileStructure($import, $template);
        if (! empty($structureErrors)) {
            $import->update([
                'status' => SSAImportStatus::FAILED,
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
            'processed_rows' => $import->rows()->whereIn('status', [SSAImportRowStatus::DONE, SSAImportRowStatus::DUPLICATE, SSAImportRowStatus::VALIDATION_ERROR])->count(),
        ]);
    }

    public function processRow(SSAImport $import, int $rowNumber, array $rowData, array $template): void
    {
        $importRow = SSAImportRow::where('ssa_import_id', $import->id)
            ->where('row_number', $rowNumber)
            ->first();

        if (! $importRow) {
            return;
        }

        // Update row status to processing
        $importRow->update([
            'status' => SSAImportRowStatus::PROCESSING,
            'raw_data' => $rowData,
        ]);

        try {
            // Map columns using template
            $mappedData = $this->mapColumns($rowData, $template);

            // Lookup student
            $student = $this->lookupStudent($mappedData);
            if (! $student) {
                $identifier = $mappedData['student_email'] ?? $mappedData['student_id_number'] ?? 'unknown';
                $importRow->update([
                    'status' => SSAImportRowStatus::VALIDATION_ERROR,
                    'error_message' => 'Student not found. Please provide either student_email or student_id_number (with school_name).',
                    'processed_at' => now(),
                ]);

                return;
            }

            // Lookup primary service
            $primaryServiceName = $mappedData['primary_service_name'] ?? null;
            if (! $primaryServiceName) {
                $importRow->update([
                    'status' => SSAImportRowStatus::VALIDATION_ERROR,
                    'error_message' => 'Primary service name is required.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $primaryService = $this->lookupService($primaryServiceName);
            if (! $primaryService) {
                $importRow->update([
                    'status' => SSAImportRowStatus::VALIDATION_ERROR,
                    'error_message' => "Service with name '{$primaryServiceName}' not found.",
                    'processed_at' => now(),
                ]);

                return;
            }

            // Lookup additional services
            $additionalServiceIds = [];
            if (! empty($mappedData['additional_service_names'])) {
                $serviceNames = array_map('trim', explode(',', $mappedData['additional_service_names']));
                foreach ($serviceNames as $serviceName) {
                    $service = $this->lookupService(trim($serviceName));
                    if (! $service) {
                        $importRow->update([
                            'status' => SSAImportRowStatus::VALIDATION_ERROR,
                            'error_message' => "Additional service '{$serviceName}' not found.",
                            'processed_at' => now(),
                        ]);

                        return;
                    }
                    $additionalServiceIds[] = $service->id;
                }
            }

            // Lookup therapist (optional)
            $therapistId = null;
            if (! empty($mappedData['assigned_therapist_email'])) {
                $therapist = $this->lookupTherapist($mappedData['assigned_therapist_email']);
                if (! $therapist) {
                    $importRow->update([
                        'status' => SSAImportRowStatus::VALIDATION_ERROR,
                        'error_message' => "Therapist with email '{$mappedData['assigned_therapist_email']}' not found.",
                        'processed_at' => now(),
                    ]);

                    return;
                }
                $therapistId = $therapist->id;
            }

            // Calculate THO minutes if not provided
            $thoMinutes = (int) ($mappedData['tho_minutes'] ?? 0);
            if ($thoMinutes === 0 && ! empty($mappedData['frequency']) && ! empty($mappedData['sessions_per_frequency'])) {
                $thoMinutes = $this->ssaService->calculateThoMinutes(
                    (int) $mappedData['minutes_per_session'],
                    $mappedData['frequency'],
                    (int) $mappedData['sessions_per_frequency'],
                    $mappedData['start_date'],
                    $mappedData['end_date']
                );
            }

            // Add calculated THO minutes to mapped data for validation
            $mappedData['tho_minutes'] = $thoMinutes;

            // Validate row data
            $validationErrors = $this->validateRowData($mappedData, $primaryService, $additionalServiceIds);

            if (! empty($validationErrors)) {
                $importRow->update([
                    'status' => SSAImportRowStatus::VALIDATION_ERROR,
                    'error_message' => implode('; ', $validationErrors),
                    'processed_at' => now(),
                ]);

                return;
            }

            // Check for duplicates (overlapping SSAs)
            $duplicateCheck = $this->checkDuplicate($student->id, $primaryService->id, $mappedData['start_date'], $mappedData['end_date']);
            if ($duplicateCheck !== null) {
                $importRow->update([
                    'status' => SSAImportRowStatus::DUPLICATE,
                    'error_message' => $duplicateCheck,
                    'processed_at' => now(),
                ]);

                return;
            }

            // Prepare CreateSSADTO
            $createData = [
                'student_id' => $student->id,
                'primary_service_id' => $primaryService->id,
                'additional_service_ids' => $additionalServiceIds,
                'start_date' => $mappedData['start_date'],
                'end_date' => $mappedData['end_date'],
                'minutes_per_session' => (int) $mappedData['minutes_per_session'],
                'frequency' => ! empty($mappedData['frequency']) ? ServiceFrequency::from($mappedData['frequency']) : null,
                'sessions_per_frequency' => ! empty($mappedData['sessions_per_frequency']) ? (int) $mappedData['sessions_per_frequency'] : null,
                'calculated_minutes' => ! empty($mappedData['calculated_minutes']) ? (int) $mappedData['calculated_minutes'] : null,
                'adjusted_minutes' => ! empty($mappedData['adjusted_minutes']) ? (int) $mappedData['adjusted_minutes'] : null,
                'adjustment_notes' => $mappedData['adjustment_notes'] ?? null,
                'tho_minutes' => $thoMinutes,
                'assigned_therapist_id' => $therapistId,
            ];

            $createDTO = CreateSSADTO::fromArray($createData);

            // Create SSA
            $ssa = $this->ssaService->create($createDTO);

            // Update row status to done
            $importRow->update([
                'status' => SSAImportRowStatus::DONE,
                'ssa_id' => $ssa->id,
                'processed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $importRow->update([
                'status' => SSAImportRowStatus::VALIDATION_ERROR,
                'error_message' => 'Failed to process row: '.$e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }

    public function lookupStudent(array $mappedData): ?User
    {
        // Try email first
        if (! empty($mappedData['student_email'])) {
            $student = $this->studentRepository->findByEmail($mappedData['student_email']);
            if ($student && $student->status === UserStatus::ACTIVE) {
                return $student;
            }
        }

        // Try ID number with school name
        if (! empty($mappedData['student_id_number']) && ! empty($mappedData['school_name'])) {
            $school = $this->schoolRepository->findByExternalEmrName($mappedData['school_name']);
            if ($school) {
                $studentProfile = $this->studentRepository->findByIdNumber($mappedData['student_id_number'], $school->id);
                if ($studentProfile && $studentProfile->user && $studentProfile->user->status === UserStatus::ACTIVE) {
                    return $studentProfile->user;
                }
            }
        }

        return null;
    }

    public function lookupService(string $serviceName): ?Service
    {
        return Service::query()
            ->where('name', $serviceName)
            ->where('status', ServiceStatus::ACTIVE)
            ->first();
    }

    public function lookupTherapist(string $email): ?User
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user && $user->role === Role::THERAPIST && $user->status === UserStatus::ACTIVE) {
            return $user;
        }

        return null;
    }

    public function validateFileStructure(SSAImport $import, array $template): array
    {
        $errors = [];

        $fileContent = $this->storageService->get($import->file_path);
        if ($fileContent === null) {
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

        if ($headers === false) {
            return ['File appears to be empty or invalid.'];
        }

        // Normalize headers
        $headers = array_map('trim', $headers);

        $errors = [];

        // Check required columns (at least one student identifier is required)
        $requiredColumns = $template['required_columns'] ?? [];
        $missingColumns = [];

        foreach ($requiredColumns as $requiredColumn) {
            if (! in_array($requiredColumn, $headers, true)) {
                $missingColumns[] = $requiredColumn;
            }
        }

        // Special check: need either student_email OR (student_id_number + school_name)
        if (! in_array('student_email', $headers, true) &&
            (! in_array('student_id_number', $headers, true) || ! in_array('school_name', $headers, true))) {
            $errors[] = 'Either student_email or (student_id_number and school_name) must be provided.';
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
        if ($fileContent === null) {
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
        if ($headers === false) {
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

    public function validateRowData(array $data, Service $primaryService, array $additionalServiceIds): array
    {
        $errors = [];

        // Basic validation rules
        $rules = [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'minutes_per_session' => ['required', 'integer', 'min:5', 'max:1440'],
            'tho_minutes' => ['required', 'integer', 'min:0'],
            'frequency' => ['nullable', 'string'],
            'sessions_per_frequency' => ['nullable', 'integer', 'min:1', 'max:100'],
            'calculated_minutes' => ['nullable', 'integer', 'min:0'],
            'adjusted_minutes' => ['nullable', 'integer'],
            'adjustment_notes' => ['nullable', 'string', 'max:65535'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = array_merge($errors, $validator->errors()->all());
        }

        // Validate frequency if service is frequency-based
        if ($primaryService->is_frequency_service) {
            if (empty($data['frequency'])) {
                $errors[] = 'Frequency is required when the primary service supports frequency.';
            } else {
                try {
                    ServiceFrequency::from($data['frequency']);
                } catch (\ValueError $e) {
                    $errors[] = 'Invalid frequency value.';
                }
            }

            if (empty($data['sessions_per_frequency'])) {
                $errors[] = 'Sessions per frequency is required when the primary service supports frequency.';
            }
        }

        // Validate additional services are indirect
        if (! empty($additionalServiceIds)) {
            $additionalServices = Service::whereIn('id', $additionalServiceIds)
                ->where('status', ServiceStatus::ACTIVE)
                ->get();

            if ($additionalServices->count() !== count($additionalServiceIds)) {
                $errors[] = 'One or more additional services not found or inactive.';
            } else {
                foreach ($additionalServices as $service) {
                    if ($service->is_direct_service) {
                        $errors[] = "Additional service '{$service->name}' must be an indirect service.";
                    }
                }
            }
        }

        return $errors;
    }

    public function checkDuplicate(int $studentId, int $serviceId, string $startDate, string $endDate): ?string
    {
        $overlapping = $this->ssaRepository->checkOverlappingSSAs($studentId, $serviceId, $startDate, $endDate);

        if ($overlapping->isNotEmpty()) {
            return 'An active or pending SSA already exists for this student and service within the specified date range.';
        }

        return null;
    }

    public function getTemplate(SSAImportType $type): array
    {
        $config = config('ssa-import');
        $typeKey = $type->value;

        return $config['templates'][$typeKey] ?? [];
    }

    private function storeFile(UploadedFile $file): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $filename = now()->format('Ymd_His').'_'.Str::random(8).'_'.$file->getClientOriginalName();

        $path = config('ssa-import.s3.path_prefix', 'ssa-imports')."/{$year}/{$month}/{$filename}";

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

    private function createImportRowRecords(SSAImport $import, int $totalRows): void
    {
        $rows = [];
        for ($i = 1; $i <= $totalRows; $i++) {
            $rows[] = [
                'ssa_import_id' => $import->id,
                'row_number' => $i,
                'status' => SSAImportRowStatus::PENDING,
                'raw_data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert in chunks to avoid memory issues
        foreach (array_chunk($rows, 500) as $chunk) {
            SSAImportRow::insert($chunk);
        }
    }
}
