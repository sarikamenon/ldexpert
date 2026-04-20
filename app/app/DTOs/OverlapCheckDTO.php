<?php

declare(strict_types=1);

namespace App\DTOs;

final class OverlapCheckDTO
{
    public function __construct(
        public readonly string $date,
        public readonly string $startTime,
        public readonly string $endTime,
    ) {}
}
