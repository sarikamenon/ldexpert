<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\PositionStatus;

final class PositionFilterDTO
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?PositionStatus $status = null,
        public readonly int $perPage = 25,
    ) {}

    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? null;

        return new self(
            search: $data['search'] ?? null,
            status: $status instanceof PositionStatus || $status === null || $status === ''
                ? ($status instanceof PositionStatus ? $status : null)
                : PositionStatus::from($status),
            perPage: isset($data['per_page']) ? (int) $data['per_page'] : 25,
        );
    }
}
