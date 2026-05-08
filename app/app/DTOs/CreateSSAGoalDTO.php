<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SSAGoalStatus;

final class CreateSSAGoalDTO
{
    public function __construct(
        public readonly int $ssaId,
        public readonly int $studentId,
        public readonly string $number,
        public readonly string $objective,
        public readonly ?string $progress,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ssaId: (int) $data['ssa_id'],
            studentId: (int) $data['student_id'],
            number: (string) $data['number'],
            objective: (string) $data['objective'],
            progress: isset($data['progress']) && $data['progress'] !== '' ? (string) $data['progress'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'ssa_id' => $this->ssaId,
            'student_id' => $this->studentId,
            'number' => $this->number,
            'objective' => $this->objective,
            'progress' => $this->progress,
            'status' => SSAGoalStatus::ACTIVE->value,
        ];
    }
}
