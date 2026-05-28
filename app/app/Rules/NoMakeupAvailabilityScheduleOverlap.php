<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Time\UserTimezoneService;
use App\Enums\ScheduleStatus;
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
            $endUtc   = $this->timezoneService->parseUserLocalToUtc($this->date.' '.$value.':00', $this->therapist);
        } catch (\Throwable) {
            return;
        }

        $conflict = Schedule::query()
            ->where('therapist_id', $this->therapist->id)
            ->where('schedule_date', $startUtc->toDateString())
            ->where('status', ScheduleStatus::SCHEDULED->value)
            ->where('start_time', '<', $endUtc->format('H:i:s'))
            ->where('end_time', '>', $startUtc->format('H:i:s'))
            ->exists();

        if ($conflict) {
            $fail('This availability window overlaps with an existing scheduled session. Please adjust the time range.');
        }
    }
}
