<?php

declare(strict_types=1);

namespace App\Domain\Time;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class UserTimezoneService
{
    public function __construct(
        private readonly string $defaultTimezone = 'UTC',
    ) {}

    private function resolveTimezone(?User $user, ?string $overrideTz = null): string
    {
        if ($overrideTz !== null && $overrideTz !== '') {
            return $overrideTz;
        }

        if ($user !== null && $user->timezone) {
            return (string) $user->timezone;
        }

        return $this->defaultTimezone;
    }

    public function parseUserLocalToUtc(
        string $dateTimeString,
        ?User $user = null,
        ?string $overrideTz = null,
    ): CarbonInterface {
        $userTz = $this->resolveTimezone($user, $overrideTz);

        return Carbon::parse($dateTimeString, $userTz)->setTimezone('UTC');
    }

    public function toUserTimezone(
        CarbonInterface $utc,
        ?User $user = null,
        ?string $overrideTz = null,
    ): CarbonInterface {
        $userTz = $this->resolveTimezone($user, $overrideTz);

        return $utc->copy()->setTimezone($userTz);
    }

    /**
     * Compute the UTC range corresponding to a user's local day.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function userDayUtcRange(
        string|CarbonInterface $localDate,
        ?User $user = null,
        ?string $overrideTz = null,
    ): array {
        $userTz = $this->resolveTimezone($user, $overrideTz);

        $date = $localDate instanceof CarbonInterface
            ? $localDate->copy()->setTimezone($userTz)
            : Carbon::parse($localDate, $userTz);

        $startOfDayLocal = $date->copy()->startOfDay();
        $endOfDayLocal = $date->copy()->endOfDay();

        return [
            $startOfDayLocal->copy()->setTimezone('UTC'),
            $endOfDayLocal->copy()->setTimezone('UTC'),
        ];
    }
}
