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

    public function resolveTimezone(?User $user, ?string $overrideTz = null): string
    {
        if ($overrideTz !== null && $overrideTz !== '') {
            return $overrideTz;
        }

        if ($user !== null) {
            $userTz = (string) ($user->timezone ?? '');

            // Treat "UTC" as a sentinel for "not yet backfilled" and prefer
            // the profile timezone when available. This matches the documented
            // fallback in CLAUDE.md and protects rows where users.timezone
            // defaulted to UTC before the backfill migration ran.
            if ($userTz !== '' && $userTz !== 'UTC') {
                return $userTz;
            }

            $therapistProfile = $user->therapistProfile;
            $studentProfile = $user->studentProfile;
            $profileTz = (string) (
                ($therapistProfile !== null ? $therapistProfile->timezone : null)
                ?? ($studentProfile !== null ? $studentProfile->timezone : null)
                ?? ''
            );

            if ($profileTz !== '') {
                return $profileTz;
            }

            if ($userTz === 'UTC') {
                return 'UTC';
            }
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
     * Convert a session-log payload's user-local session_date / start_time /
     * end_time fields to UTC, using the supplied user's timezone. Idempotent:
     * if session_date is missing, the payload is returned unchanged.
     *
     * Used by SessionLogService and SessionLogImportRowProcessor — keep the
     * single implementation here so write sites stay in sync.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function convertSessionLocalToUtc(array $data, User $user): array
    {
        $sessionDateLocal = $data['session_date'] ?? null;
        $startLocal = $data['start_time'] ?? null;
        $endLocal = $data['end_time'] ?? null;

        if (! is_string($sessionDateLocal) || $sessionDateLocal === '') {
            return $data;
        }

        if (is_string($startLocal) && $startLocal !== '') {
            // start_time may arrive as "Y-m-d H:i:s" or just "H:i" — normalize.
            $startStr = str_contains($startLocal, ' ')
                ? $startLocal
                : $sessionDateLocal.' '.$startLocal;
            $startUtc = $this->parseUserLocalToUtc($startStr, $user);
            $data['start_time'] = $startUtc->format('Y-m-d H:i:s');
            $data['session_date'] = $startUtc->format('Y-m-d');
        } else {
            // No time supplied — anchor to noon local so the UTC date matches
            // the calendar date the user typed regardless of TZ offset
            // direction (midnight would roll back a day for east-of-UTC TZs).
            $startUtc = $this->parseUserLocalToUtc($sessionDateLocal.' 12:00:00', $user);
            $data['session_date'] = $startUtc->format('Y-m-d');
        }

        if (is_string($endLocal) && $endLocal !== '') {
            $endStr = str_contains($endLocal, ' ')
                ? $endLocal
                : $sessionDateLocal.' '.$endLocal;
            $endUtc = $this->parseUserLocalToUtc($endStr, $user);
            $data['end_time'] = $endUtc->format('Y-m-d H:i:s');
        }

        return $data;
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
