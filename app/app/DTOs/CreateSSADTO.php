<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceFrequency;
use App\Enums\SSAStatus;

final class CreateSSADTO
{
    public function __construct(
        public readonly int $studentId,
        public readonly int $primaryServiceId,
        /**
         * @var array<int>
         */
        public readonly array $additionalServiceIds,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $minutesPerSession,
        public readonly ?ServiceFrequency $frequency,
        public readonly ?int $sessionsPerFrequency,
        public readonly ?int $calculatedMinutes,
        public readonly ?int $adjustedMinutes,
        public readonly ?string $adjustmentNotes,
        public readonly int $thoMinutes,
        public readonly ?int $assignedTherapistId,
    ) {}

    public static function fromArray(array $data): self
    {
        $frequency = null;
        if (isset($data['frequency']) && $data['frequency'] !== '') {
            $frequency = $data['frequency'] instanceof ServiceFrequency
                ? $data['frequency']
                : ServiceFrequency::from($data['frequency']);
        }

        return new self(
            studentId: (int) $data['student_id'],
            primaryServiceId: (int) $data['primary_service_id'],
            additionalServiceIds: collect($data['additional_service_ids'] ?? [])
                ->filter(static fn ($value) => $value !== null && $value !== '' && is_numeric($value))
                ->map(static fn ($value) => (int) $value)
                ->filter(static fn (int $value) => $value > 0)
                ->unique()
                ->values()
                ->all(),
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            minutesPerSession: (int) $data['minutes_per_session'],
            frequency: $frequency,
            sessionsPerFrequency: isset($data['sessions_per_frequency']) && $data['sessions_per_frequency'] !== ''
                ? (int) $data['sessions_per_frequency']
                : null,
            calculatedMinutes: isset($data['calculated_minutes']) && $data['calculated_minutes'] !== ''
                ? (int) $data['calculated_minutes']
                : null,
            adjustedMinutes: isset($data['adjusted_minutes']) && $data['adjusted_minutes'] !== ''
                ? (int) $data['adjusted_minutes']
                : null,
            adjustmentNotes: $data['adjustment_notes'] ?? null,
            thoMinutes: (int) $data['tho_minutes'],
            assignedTherapistId: isset($data['assigned_therapist_id']) && $data['assigned_therapist_id'] !== ''
                ? (int) $data['assigned_therapist_id']
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'primary_service_id' => $this->primaryServiceId,
            'additional_service_ids' => $this->additionalServiceIds,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'minutes_per_session' => $this->minutesPerSession,
            'frequency' => $this->frequency?->value ?? ServiceFrequency::WEEKLY->value,
            'sessions_per_frequency' => $this->sessionsPerFrequency ?? ($this->frequency === null ? 1 : null),
            'calculated_minutes' => $this->calculatedMinutes,
            'adjusted_minutes' => $this->adjustedMinutes,
            'adjustment_notes' => $this->adjustmentNotes,
            'tho_minutes' => $this->thoMinutes,
            'assigned_therapist_id' => $this->assignedTherapistId,
            'status' => SSAStatus::PENDING->value, // New SSAs always start as PENDING
        ];
    }
}
