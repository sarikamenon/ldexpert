<?php

declare(strict_types=1);

namespace App\DTOs;

final class SSAMinutesSummaryDTO
{
    public function __construct(
        public readonly int $thoMinutes,
        public readonly int $scheduledMinutes,
        public readonly int $loggedMinutes,
        public readonly int $approvedMinutes,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            thoMinutes: (int) ($data['tho_minutes'] ?? 0),
            scheduledMinutes: (int) ($data['scheduled_minutes'] ?? 0),
            loggedMinutes: (int) ($data['logged_minutes'] ?? 0),
            approvedMinutes: (int) ($data['approved_minutes'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tho_minutes' => $this->thoMinutes,
            'scheduled_minutes' => $this->scheduledMinutes,
            'logged_minutes' => $this->loggedMinutes,
            'approved_minutes' => $this->approvedMinutes,
            'remaining_minutes' => $this->getRemainingMinutes(),
            'approved_utilization_percentage' => $this->getApprovedUtilizationPercentage(),
        ];
    }

    public function getRemainingMinutes(): int
    {
        return max(0, $this->thoMinutes - $this->approvedMinutes);
    }

    public function getThoHours(): float
    {
        return round($this->thoMinutes / 60, 2);
    }

    public function getScheduledHours(): float
    {
        return round($this->scheduledMinutes / 60, 2);
    }

    public function getLoggedHours(): float
    {
        return round($this->loggedMinutes / 60, 2);
    }

    public function getApprovedHours(): float
    {
        return round($this->approvedMinutes / 60, 2);
    }

    public function getApprovedUtilizationPercentage(): float
    {
        if ($this->thoMinutes <= 0) {
            return 0.0;
        }

        return round(($this->approvedMinutes / $this->thoMinutes) * 100, 1);
    }
}
