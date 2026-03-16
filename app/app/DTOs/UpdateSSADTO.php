<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceFrequency;

final class UpdateSSADTO
{
    public function __construct(
        public readonly ?int $assignedTherapistId,
        /**
         * @var array<int>|null
         */
        public readonly ?array $additionalServiceIds,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?int $minutesPerSession,
        public readonly ?ServiceFrequency $frequency,
        public readonly ?int $sessionsPerFrequency,
        public readonly ?int $calculatedMinutes,
        public readonly ?int $adjustedMinutes,
        public readonly ?string $adjustmentNotes,
        public readonly ?string $additionalNotes,
        public readonly ?int $thoMinutes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $frequency = null;
        if (isset($data['frequency']) && $data['frequency'] !== '') {
            $frequency = $data['frequency'] instanceof ServiceFrequency
                ? $data['frequency']
                : ServiceFrequency::from($data['frequency']);
        }

        $additionalServiceIds = null;
        if (array_key_exists('additional_service_ids', $data)) {
            /** @var array<int, mixed> $rawIds */
            $rawIds = $data['additional_service_ids'] ?? [];
            $additionalServiceIds = collect($rawIds)
                ->filter(static fn ($value): bool => $value !== null && $value !== '')
                ->map(static fn ($value): int => (int) $value)
                ->unique()
                ->values()
                ->all();
        }

        return new self(
            assignedTherapistId: array_key_exists('assigned_therapist_id', $data)
                ? ($data['assigned_therapist_id'] !== null && $data['assigned_therapist_id'] !== '' ? (int) $data['assigned_therapist_id'] : null)
                : -1,
            additionalServiceIds: $additionalServiceIds,
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
            minutesPerSession: isset($data['minutes_per_session']) ? (int) $data['minutes_per_session'] : null,
            frequency: $frequency,
            sessionsPerFrequency: isset($data['sessions_per_frequency']) ? (int) $data['sessions_per_frequency'] : null,
            calculatedMinutes: isset($data['calculated_minutes']) && $data['calculated_minutes'] !== ''
                ? (int) $data['calculated_minutes']
                : null,
            adjustedMinutes: isset($data['adjusted_minutes']) && $data['adjusted_minutes'] !== ''
                ? (int) $data['adjusted_minutes']
                : null,
            adjustmentNotes: $data['adjustment_notes'] ?? null,
            additionalNotes: $data['additional_notes'] ?? null,
            thoMinutes: isset($data['tho_minutes']) ? (int) $data['tho_minutes'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = [];

        if ($this->assignedTherapistId !== -1) {
            $array['assigned_therapist_id'] = $this->assignedTherapistId;
        }
        if ($this->startDate !== null) {
            $array['start_date'] = $this->startDate;
        }
        if ($this->endDate !== null) {
            $array['end_date'] = $this->endDate;
        }
        if ($this->minutesPerSession !== null) {
            $array['minutes_per_session'] = $this->minutesPerSession;
        }
        if ($this->frequency !== null) {
            $array['frequency'] = $this->frequency->value;
        }
        if ($this->sessionsPerFrequency !== null) {
            $array['sessions_per_frequency'] = $this->sessionsPerFrequency;
        }
        if ($this->calculatedMinutes !== null) {
            $array['calculated_minutes'] = $this->calculatedMinutes;
        }
        if ($this->adjustedMinutes !== null) {
            $array['adjusted_minutes'] = $this->adjustedMinutes;
        }
        if ($this->adjustmentNotes !== null) {
            $array['adjustment_notes'] = $this->adjustmentNotes;
        }
        if ($this->additionalNotes !== null) {
            $array['additional_notes'] = $this->additionalNotes;
        }
        if ($this->thoMinutes !== null) {
            $array['tho_minutes'] = $this->thoMinutes;
        }

        return $array;
    }
}
