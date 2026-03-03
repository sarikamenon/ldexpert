<?php

declare(strict_types=1);

namespace App\DTOs;

final class DataTablesParamsDTO
{
    public function __construct(
        public readonly int $draw,
        public readonly int $start,
        public readonly int $length,
        public readonly ?string $searchValue,
        public readonly ?string $orderColumn,
        public readonly string $orderDir,
    ) {}
}
