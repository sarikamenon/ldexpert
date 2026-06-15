<?php

declare(strict_types=1);

namespace App\DTOs;

final class UpdateSSAGoalDTO
{
    public function __construct(
        public readonly string $number,
        public readonly string $objective,
        public readonly ?string $progress,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            number: (string) $data['number'],
            objective: (string) $data['objective'],
            progress: isset($data['progress']) && $data['progress'] !== '' ? (string) $data['progress'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'objective' => $this->objective,
            'progress' => $this->progress,
        ];
    }
}
