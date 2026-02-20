<?php

declare(strict_types=1);

namespace App\DTOs;

final class ImportSSADTO
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

    public function toCreateSSADTO(): CreateSSADTO
    {
        $data = $this->data;

        // Parse additional service names (comma-separated) into IDs
        $additionalServiceIds = [];
        if (isset($data['additional_service_names']) && ! empty($data['additional_service_names'])) {
            $serviceNames = array_map('trim', explode(',', $data['additional_service_names']));
            // Service IDs will be resolved during import processing
            $data['additional_service_ids'] = $serviceNames; // Store names temporarily
        } else {
            $data['additional_service_ids'] = [];
        }

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
