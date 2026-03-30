<?php

declare(strict_types=1);

namespace App\DTOs;

final class CreateQGlobRequestDTO
{
    public function __construct(
        public readonly int $requestedById,
        public readonly int $studentId,
        public readonly string $requestedDate,
        public readonly string $requestedTime,
        public readonly ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            requestedById: (int) $data['requested_by_id'],
            studentId: (int) $data['student_id'],
            requestedDate: (string) $data['requested_date'],
            requestedTime: (string) $data['requested_time'],
            note: isset($data['note']) ? (string) $data['note'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requested_by_id' => $this->requestedById,
            'student_id' => $this->studentId,
            'requested_date' => $this->requestedDate,
            'requested_time' => $this->requestedTime,
            'note' => $this->note,
        ];
    }
}
