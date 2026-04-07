<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\Storage\Services\StorageServiceInterface;
use App\DTOs\StoreSessionLogImportDTO;
use App\Enums\SessionLogImportRowStatus;
use App\Enums\SessionLogImportStatus;
use App\Enums\SessionLogImportType;
use App\Jobs\ProcessSessionLogImportJob;
use App\Models\SessionLogImport;
use App\Models\SessionLogImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class SessionLogImportService
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly SessionLogImportRowProcessor $rowProcessor,
    ) {}

    public function storeImportRequest(StoreSessionLogImportDTO $dto): SessionLogImport
    {
        $filePath = $this->storeFile($dto->file);
        $totalRows = $this->countCsvRows($dto->file);

        $import = SessionLogImport::create([
            'user_id' => $dto->userId,
            'type' => $dto->type,
            'file_path' => $filePath,
            'file_name' => $dto->file->getClientOriginalName(),
            'total_rows' => $totalRows,
            'status' => SessionLogImportStatus::PENDING,
        ]);

        $this->createImportRowRecords($import, $totalRows);

        ProcessSessionLogImportJob::dispatch($import);

        return $import;
    }

    public function processImport(SessionLogImport $import): void
    {
        $template = $this->getTemplate($import->type);

        $structureErrors = $this->validateFileStructure($import, $template);
        if (! empty($structureErrors)) {
            $import->update([
                'status' => SessionLogImportStatus::FAILED,
                'error_message' => implode(' ', $structureErrors),
                'completed_at' => now(),
            ]);

            return;
        }

        $rows = $this->parseCsvFromStorage($import->file_path);

        // Pre-load already-processed reference_ids for duplicate detection
        /** @var array<string, bool> $alreadyProcessed */
        $alreadyProcessed = SessionLogImportRow::query()
            ->where('status', SessionLogImportRowStatus::DONE)
            ->whereNotNull('reference_id')
            ->pluck('reference_id')
            ->filter()
            ->mapWithKeys(static fn (string $id): array => [$id => true])
            ->all();

        /** @var array<string, bool> $currentBatch */
        $currentBatch = [];

        foreach ($rows as $rowNumber => $rowData) {
            $mappedData = $this->mapColumns($rowData, $template);

            $importRow = SessionLogImportRow::where('session_log_import_id', $import->id)
                ->where('row_number', $rowNumber + 1)
                ->first();

            if (! $importRow) {
                continue;
            }

            $this->rowProcessor->process($import, $importRow, $mappedData, $alreadyProcessed, $currentBatch);
        }

        // Update import counts
        $import->refresh();
        $import->update([
            'processed_rows' => $import->rows()->whereIn('status', [
                SessionLogImportRowStatus::DONE,
                SessionLogImportRowStatus::DUPLICATE,
                SessionLogImportRowStatus::VALIDATION_ERROR,
                SessionLogImportRowStatus::SKIPPED,
            ])->count(),
            'successful_rows' => $import->rows()->where('status', SessionLogImportRowStatus::DONE)->count(),
            'failed_rows' => $import->rows()->where('status', SessionLogImportRowStatus::VALIDATION_ERROR)->count(),
            'skipped_rows' => $import->rows()->whereIn('status', [
                SessionLogImportRowStatus::SKIPPED,
                SessionLogImportRowStatus::DUPLICATE,
            ])->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(SessionLogImportType $type): array
    {
        $config = config('session-log-import');
        $typeKey = $type->value;

        return $config['templates'][$typeKey] ?? [];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<int, string>
     */
    public function validateFileStructure(SessionLogImport $import, array $template): array
    {
        $fileContent = $this->storageService->get($import->file_path);
        if ($fileContent === null) {
            return ['Unable to read file from storage.'];
        }

        $tempFile = tmpfile();
        if ($tempFile === false) {
            return ['Unable to parse file.'];
        }

        fwrite($tempFile, $fileContent);
        rewind($tempFile);

        $headers = fgetcsv($tempFile);
        fclose($tempFile);

        if ($headers === false) {
            return ['File appears to be empty or invalid.'];
        }

        $headers = array_map(static fn ($v): string => trim((string) $v), $headers);

        $errors = [];
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

    /**
     * @return array<int, array<string, string>>
     */
    public function parseCsvFromStorage(string $filePath): array
    {
        $rows = [];
        $fileContent = $this->storageService->get($filePath);
        if ($fileContent === null) {
            return [];
        }

        $tempFile = tmpfile();
        if ($tempFile === false) {
            return [];
        }

        fwrite($tempFile, $fileContent);
        rewind($tempFile);

        $headers = fgetcsv($tempFile);
        if ($headers === false) {
            fclose($tempFile);

            return [];
        }

        $headers = array_map(static fn ($v): string => trim((string) $v), $headers);

        while (($row = fgetcsv($tempFile)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = trim((string) ($row[$index] ?? ''));
            }
            $rows[] = $rowData;
        }

        fclose($tempFile);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array<string, mixed>  $template
     * @return array<string, string>
     */
    public function mapColumns(array $rowData, array $template): array
    {
        $mapped = [];
        $columnMapping = $template['column_mapping'] ?? [];

        foreach ($columnMapping as $csvColumn => $fieldName) {
            if (isset($rowData[$csvColumn])) {
                $mapped[$fieldName] = trim((string) $rowData[$csvColumn]);
            }
        }

        return $mapped;
    }

    private function storeFile(UploadedFile $file): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $filename = now()->format('Ymd_His').'_'.Str::random(8).'_'.$file->getClientOriginalName();

        $path = config('session-log-import.storage.path_prefix', 'session-log-imports')."/{$year}/{$month}/{$filename}";

        $this->storageService->put($path, (string) file_get_contents($file->getRealPath()));

        return $path;
    }

    private function countCsvRows(UploadedFile $file): int
    {
        $count = 0;
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if (! empty(array_filter($row))) {
                $count++;
            }
        }

        fclose($handle);

        return $count;
    }

    private function createImportRowRecords(SessionLogImport $import, int $totalRows): void
    {
        $rows = [];
        for ($i = 1; $i <= $totalRows; $i++) {
            $rows[] = [
                'session_log_import_id' => $import->id,
                'row_number' => $i,
                'status' => SessionLogImportRowStatus::PENDING,
                'raw_data' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SessionLogImportRow::insert($chunk);
        }
    }
}
