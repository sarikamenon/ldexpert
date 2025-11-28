<?php

declare(strict_types=1);

namespace App\DTOs;

final class ScheduleFilterDTO
{
    public function __construct(
        public readonly ?string $date = null,
        public readonly ?int $schoolId = null,
        public readonly ?int $studentId = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            date: $data['date'] ?? null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            studentId: isset($data['student_id']) && $data['student_id'] !== ''
                ? (int) $data['student_id']
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'school_id' => $this->schoolId,
            'student_id' => $this->studentId,
        ];
    }
}
