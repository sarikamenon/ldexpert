<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SchoolStatus;

final class SchoolFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?SchoolStatus $status = null,
        public readonly bool $includeDeactivated = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $statusValue = $data['status'] ?? null;

        // Parse status if provided
        $status = null;
        if (isset($statusValue) && $statusValue !== null && $statusValue !== '') {
            $status = $statusValue instanceof SchoolStatus
                ? $statusValue
                : SchoolStatus::from($statusValue);
        }

        // When no specific status is selected (All Statuses), include deactivated schools
        $includeDeactivated = $status === null
            ? true
            : (bool) ($data['include_deactivated'] ?? $data['show_deactivated'] ?? false);

        return new self(
            search: $data['search'] ?? null,
            status: $status,
            includeDeactivated: $includeDeactivated,
        );
    }
}
