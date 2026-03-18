<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum ServiceFrequency: string
{
    case ONE_TIME = 'one_time';
    case WEEKLY = 'weekly';
    case BI_WEEKLY = 'bi_weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::ONE_TIME => 'One Time',
            self::WEEKLY => 'Weekly',
            self::BI_WEEKLY => 'Bi-weekly',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $frequency): string => $frequency->value,
            self::cases()
        );
    }

    public function usesFixedSingleOccurrence(): bool
    {
        return $this === self::ONE_TIME;
    }

    public function normalizeSessionsPerFrequency(?int $sessionsPerFrequency): ?int
    {
        if ($this->usesFixedSingleOccurrence()) {
            return 1;
        }

        return $sessionsPerFrequency;
    }

    public function normalizeCalculatedMinutes(?int $minutesPerSession, ?int $calculatedMinutes): ?int
    {
        if ($this->usesFixedSingleOccurrence() && $minutesPerSession !== null) {
            return $minutesPerSession;
        }

        return $calculatedMinutes;
    }

    public function occurrencesInDateRange(CarbonInterface $startDate, CarbonInterface $endDate): int
    {
        if ($this->usesFixedSingleOccurrence()) {
            return 1;
        }

        $daysDiff = $startDate->diffInDays($endDate) + 1;

        return max(1, (int) ceil($daysDiff * $this->dateRangeMultiplier()));
    }

    private function dateRangeMultiplier(): float
    {
        return match ($this) {
            self::ONE_TIME => 0,
            self::WEEKLY => 52 / 365,
            self::BI_WEEKLY => 26 / 365,
            self::MONTHLY => 12 / 365,
            self::QUARTERLY => 4 / 365,
        };
    }
}
