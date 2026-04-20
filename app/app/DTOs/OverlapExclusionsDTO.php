<?php

declare(strict_types=1);

namespace App\DTOs;

final class OverlapExclusionsDTO
{
    public function __construct(
        public readonly ?int $scheduleId = null,
        public readonly ?string $batchNumber = null,
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
