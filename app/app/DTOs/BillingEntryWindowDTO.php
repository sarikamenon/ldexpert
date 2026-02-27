<?php

declare(strict_types=1);

namespace App\DTOs;

final class BillingEntryWindowDTO
{
    public function __construct(
        public readonly string $sessionDate,
        public readonly string $weekStart,
        public readonly string $cutoff,
        public readonly bool $isWithinWindow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionDate: (string) ($data['session_date'] ?? ''),
            weekStart: (string) ($data['week_start'] ?? ''),
            cutoff: (string) ($data['cutoff'] ?? ''),
            isWithinWindow: (bool) ($data['is_within_window'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_date' => $this->sessionDate,
            'week_start' => $this->weekStart,
            'cutoff' => $this->cutoff,
            'is_within_window' => $this->isWithinWindow,
        ];
    }
}
