<?php

declare(strict_types=1);

namespace App\DTOs;

final class PayStubReportFilterDTO
{
    public function __construct(
        public readonly int $year,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            year: (int) ($data['year'] ?? date('Y')),
        );
    }
}
