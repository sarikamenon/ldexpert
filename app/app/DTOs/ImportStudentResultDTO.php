<?php

declare(strict_types=1);

namespace App\DTOs;

final class ImportStudentResultDTO
{
    public function __construct(
        public readonly int $totalRows = 0,
        public readonly int $successCount = 0,
        public readonly int $skippedCount = 0,
        public readonly int $errorCount = 0,
        public readonly array $errors = [],
        public readonly array $skippedRows = [],
    ) {}

    public static function create(): self
    {
        return new self;
    }

    public function withTotalRows(int $totalRows): self
    {
        return new self(
            totalRows: $totalRows,
            successCount: $this->successCount,
            skippedCount: $this->skippedCount,
            errorCount: $this->errorCount,
            errors: $this->errors,
            skippedRows: $this->skippedRows,
        );
    }

    public function withSuccess(int $count): self
    {
        return new self(
            totalRows: $this->totalRows,
            successCount: $this->successCount + $count,
            skippedCount: $this->skippedCount,
            errorCount: $this->errorCount,
            errors: $this->errors,
            skippedRows: $this->skippedRows,
        );
    }

    public function withSkipped(int $rowNumber, string $reason): self
    {
        $skippedRows = $this->skippedRows;
        $skippedRows[] = [
            'row' => $rowNumber,
            'reason' => $reason,
        ];

        return new self(
            totalRows: $this->totalRows,
            successCount: $this->successCount,
            skippedCount: $this->skippedCount + 1,
            errorCount: $this->errorCount,
            errors: $this->errors,
            skippedRows: $skippedRows,
        );
    }

    public function withError(int $rowNumber, array $errors): self
    {
        $errorList = $this->errors;
        $errorList[] = [
            'row' => $rowNumber,
            'errors' => $errors,
        ];

        return new self(
            totalRows: $this->totalRows,
            successCount: $this->successCount,
            skippedCount: $this->skippedCount,
            errorCount: $this->errorCount + 1,
            errors: $errorList,
            skippedRows: $this->skippedRows,
        );
    }

    public function toArray(): array
    {
        return [
            'total_rows' => $this->totalRows,
            'success_count' => $this->successCount,
            'skipped_count' => $this->skippedCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors,
            'skipped_rows' => $this->skippedRows,
        ];
    }
}
