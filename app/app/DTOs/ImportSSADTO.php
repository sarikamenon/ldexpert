<?php

declare(strict_types=1);

namespace App\DTOs;

final class ImportSSADTO
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     */
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

    /** @param list<string> $errors */
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

    public function toCreateSSADTO(): CreateSSADTO
    {
        $data = $this->data;

        // Ensure frequency is null if empty
        if (empty($data['frequency'])) {
            $data['frequency'] = null;
        }

        // Ensure sessions_per_frequency is null if empty
        if (empty($data['sessions_per_frequency'])) {
            $data['sessions_per_frequency'] = null;
        }

        // Ensure optional fields are null if empty
        $optionalFields = ['calculated_minutes', 'adjusted_minutes', 'adjustment_notes', 'assigned_therapist_id'];
        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return CreateSSADTO::fromArray($data);
    }
}
