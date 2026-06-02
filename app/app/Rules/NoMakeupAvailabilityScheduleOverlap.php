<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Time\UserTimezoneService;
use App\Models\Schedule;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NoMakeupAvailabilityScheduleOverlap implements ValidationRule
{
    public function __construct(
        private readonly User $therapist,
        private readonly string $date,
        private readonly string $startTime,
        private readonly UserTimezoneService $timezoneService,
    ) {}

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            $startUtc = $this->timezoneService->parseUserLocalToUtc($this->date.' '.$this->startTime.':00', $this->therapist);
            $endUtc = $this->timezoneService->parseUserLocalToUtc($this->date.' '.$value.':00', $this->therapist);
        } catch (\Throwable) {
            return;
        }

        // Compare on the full (schedule_date + time) UTC instant, not a single
        // schedule_date with H:i:s bounds: a therapist-local window can straddle the
        // UTC day boundary, so its start and end land on different UTC dates. The
        // single-date predicate would miss the part on the second date entirely.
        $conflict = Schedule::query()
            ->forTherapistOwned($this->therapist->id)
            ->scheduled()
            ->overlappingWindow(
                $startUtc->format('Y-m-d H:i:s'),
                $endUtc->format('Y-m-d H:i:s'),
            )
            ->exists();

        if ($conflict) {
            $fail('This availability window overlaps with an existing scheduled session. Please adjust the time range.');
        }
    }
}
