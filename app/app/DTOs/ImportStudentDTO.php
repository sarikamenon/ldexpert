<?php

declare(strict_types=1);

namespace App\DTOs;

final class ImportStudentDTO
{
    public function __construct(
        public readonly array $data,
        public readonly int $rowNumber,
        public readonly array $errors = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, int $rowNumber): self
    {
        return new self(
            data: $data,
            rowNumber: $rowNumber,
        );
    }

    public function withErrors(array $errors): self
    {
        return new self(
            data: $this->data,
            rowNumber: $this->rowNumber,
            errors: $errors,
        );
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function getSchoolName(): ?string
    {
        return $this->data['school_name'] ?? null;
    }

    public function toCreateStudentDTO(string $password, int $schoolId): CreateStudentDTO
    {
        $data = $this->data;
        $data['school_id'] = $schoolId;
        unset($data['school_name']); // Remove school_name, use school_id instead

        return CreateStudentDTO::fromArray(
            array_merge($data, ['password' => $password])
        );
    }
}
