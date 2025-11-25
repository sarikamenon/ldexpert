<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ServiceFrequency;

final class UpdateSSADTO
{
    public function __construct(
        public readonly ?int $additionalServiceId,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?int $minutesPerSession,
        public readonly ?ServiceFrequency $frequency,
        public readonly ?int $sessionsPerFrequency,
        public readonly ?int $calculatedMinutes,
        public readonly ?int $adjustedMinutes,
        public readonly ?string $adjustmentNotes,
        public readonly ?int $thoMinutes,
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
            additionalServiceId: isset($data['additional_service_id']) && $data['additional_service_id'] !== '' 
                ? (int) $data['additional_service_id'] 
                : null,
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
            thoMinutes: isset($data['tho_minutes']) ? (int) $data['tho_minutes'] : null,
        );
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->additionalServiceId !== null) {
            $array['additional_service_id'] = $this->additionalServiceId;
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
        if ($this->thoMinutes !== null) {
            $array['tho_minutes'] = $this->thoMinutes;
        }

        return $array;
    }
}

